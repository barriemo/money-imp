<?php

namespace App\Domains\Reconciliation\Services;

use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use App\Models\PaymentIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReconciliationCandidateService
{
    public function __construct(
        private readonly ReconciliationEvidencePublisher $evidence,
    ) {}

    public function generate(
        bool $publishEvidence = true
    ): array {
        $stats = [
            'considered' => 0,
            'classified_non_client' => 0,
            'client_matches' => 0,
            'invoice_matches' => 0,
            'ambiguous' => 0,
            'unmatched' => 0,
        ];

        BankTransaction::query()
            ->where('match_status', 'unmatched')
            ->where('amount', '>', 0)
            ->with([
                'bankAccount',
                'paymentAllocations',
            ])
            ->chunkById(200, function ($transactions) use (&$stats): void {
                foreach ($transactions as $transaction) {
                    $stats['considered']++;

                    if (
                        $transaction
                            ->paymentAllocations
                            ->contains(
                                fn (PaymentAllocation $allocation) => $allocation->status
                                    === PaymentAllocation::STATUS_HISTORICAL_CORROBORATION
                            )
                    ) {
                        continue;
                    }

                    if ($this->looksNonClient($transaction)) {
                        $transaction->update([
                            'client_id' => null,
                            'transaction_type' => $this->classifyNonClient(
                                $transaction
                            ),
                            'match_status' => 'ignored',
                            'match_confidence' => 100,
                        ]);

                        $stats['classified_non_client']++;

                        continue;
                    }

                    $candidate = $this->bestClientCandidate($transaction);

                    if (! $candidate) {
                        $stats['unmatched']++;

                        continue;
                    }

                    if ($candidate['ambiguous']) {
                        $stats['ambiguous']++;

                        continue;
                    }

                    /** @var Client $client */
                    $client = $candidate['client'];
                    $confidence = $candidate['confidence'];

                    $transaction->update([
                        'client_id' => $client->id,
                        'transaction_type' => 'customer_payment',
                        'match_status' => 'suggested',
                        'match_confidence' => $confidence,

                        'metadata' => array_merge(
                            $transaction->metadata ?? [],
                            [
                                'reconciliation_provenance' => 'automated_candidate',
                            ]
                        ),
                    ]);

                    $stats['client_matches']++;

                    $invoice = $this->bestInvoiceCandidate(
                        $transaction,
                        $client
                    );

                    if (! $invoice) {
                        continue;
                    }

                    $existingAllocation =
                        PaymentAllocation::query()
                            ->where(
                                'bank_transaction_id',
                                $transaction->id
                            )
                            ->where(
                                'accounting_invoice_id',
                                $invoice['invoice']->id
                            )
                            ->first();

                    /*
                     * Candidate generation may refresh an existing
                     * provisional suggestion, but it must never
                     * overwrite a human/reviewed lifecycle decision.
                     *
                     * This protects rejected, approved, imported and
                     * historical corroboration states.
                     */
                    if (
                        $existingAllocation
                        && $existingAllocation->status
                            !== 'suggested'
                    ) {
                        continue;
                    }

                    PaymentAllocation::updateOrCreate(
                        [
                            'bank_transaction_id' => $transaction->id,
                            'accounting_invoice_id' => $invoice['invoice']->id,
                        ],
                        [
                            'amount' => min(
                                (float) $transaction->amount,
                                (float) $invoice['invoice']->outstanding_amount
                            ),
                            'status' => 'suggested',
                            'confidence' => min(
                                100,
                                $confidence + $invoice['bonus']
                            ),
                            'match_method' => $invoice['method'],
                            'reason' => $invoice['reason'],
                        ]
                    );

                    $stats['invoice_matches']++;
                }
            });

        if (
            $publishEvidence
            && (
                $stats['classified_non_client'] > 0
                || $stats['client_matches'] > 0
                || $stats['invoice_matches'] > 0
            )
        ) {
            $this->evidence
                ->publish(
                    type: 'reconciliation_candidates_generated',

                    metadata: $stats
                );
        }

        return $stats;
    }

    private function looksNonClient(BankTransaction $transaction): bool
    {
        $text = $this->normalise(
            ($transaction->description ?? '')
            .' '.($transaction->reference ?? '')
        );

        if (Str::contains($text, [
            'from a c',
            'via mobile xfer',
            'amex cbr',
            'american express',
        ])) {
            return true;
        }

        if (
            Str::contains($text, ['b moran', 'barrie moran'])
            && (float) $transaction->amount >= 500
        ) {
            return true;
        }

        if (
            $transaction->bankAccount?->account_type === 'CreditCardAccount'
        ) {
            return true;
        }

        return false;
    }

    private function classifyNonClient(
        BankTransaction $transaction
    ): string {
        $text = $this->normalise(
            ($transaction->description ?? '')
            .' '.($transaction->reference ?? '')
        );

        if (Str::contains($text, ['amex cbr', 'american express'])) {
            return 'card_refund_or_transfer';
        }

        if (Str::contains($text, ['from a c', 'via mobile xfer'])) {
            return 'internal_transfer';
        }

        if (
            Str::contains($text, ['b moran', 'barrie moran'])
            && (float) $transaction->amount >= 500
        ) {
            return 'director_or_internal_transfer';
        }

        if ($transaction->bankAccount?->account_type === 'CreditCardAccount') {
            return 'card_credit';
        }

        return 'non_client_income';
    }

    private function bestClientCandidate(
        BankTransaction $transaction
    ): ?array {
        $text = $this->normalise(
            ($transaction->description ?? '')
            .' '.($transaction->reference ?? '')
        );

        $candidates = collect();

        foreach (PaymentIdentity::with('client')->get() as $identity) {
            $needle = $this->normalise($identity->normalized_value);

            if ($needle !== '' && Str::contains($text, $needle)) {
                $candidates->push([
                    'client' => $identity->client,
                    'confidence' => min(
                        100,
                        max(85, (float) $identity->confidence)
                    ),
                    'reason' => 'known_payment_identity',
                ]);
            }
        }

        foreach (Client::where('status', 'active')->get() as $client) {
            $name = $this->normalise($client->name);

            if (
                strlen($name) >= 4
                && Str::contains($text, $name)
            ) {
                $candidates->push([
                    'client' => $client,
                    'confidence' => 80,
                    'reason' => 'client_name_in_bank_description',
                ]);
            }

            if ($client->legal_name) {
                $legal = $this->normalise($client->legal_name);

                if (
                    strlen($legal) >= 4
                    && Str::contains($text, $legal)
                ) {
                    $candidates->push([
                        'client' => $client,
                        'confidence' => 85,
                        'reason' => 'legal_name_in_bank_description',
                    ]);
                }
            }
        }

        $ranked = $this->collapseCandidates($candidates);

        if ($ranked->isEmpty()) {
            return null;
        }

        $first = $ranked->first();
        $second = $ranked->skip(1)->first();

        return [
            'client' => $first['client'],
            'confidence' => $first['confidence'],
            'ambiguous' => $second !== null
                && abs(
                    $first['confidence'] - $second['confidence']
                ) < 15,
        ];
    }

    private function bestInvoiceCandidate(
        BankTransaction $transaction,
        Client $client
    ): ?array {
        $invoices = AccountingInvoice::query()
            ->where('client_id', $client->id)
            ->where('outstanding_amount', '>', 0)
            ->where(function ($query) use ($transaction): void {
                $query
                    ->whereNull('invoice_date')
                    ->orWhereDate(
                        'invoice_date',
                        '<=',
                        $transaction->transaction_date
                    );
            })
            ->orderBy('due_date')
            ->get();

        if ($invoices->isEmpty()) {
            return null;
        }

        $exact = $invoices->filter(
            fn (AccountingInvoice $invoice) => abs(
                (float) $invoice->outstanding_amount
                - (float) $transaction->amount
            ) < 0.01
        );

        if ($exact->count() === 1) {
            return [
                'invoice' => $exact->first(),
                'bonus' => 20,
                'method' => 'client_and_exact_amount',
                'reason' => 'Client matched and exactly one outstanding invoice matches the payment amount.',
            ];
        }

        $text = $this->normalise(
            ($transaction->description ?? '')
            .' '.($transaction->reference ?? '')
        );

        foreach ($invoices as $invoice) {
            if (
                $invoice->invoice_number
                && Str::contains(
                    $text,
                    $this->normalise($invoice->invoice_number)
                )
            ) {
                return [
                    'invoice' => $invoice,
                    'bonus' => 25,
                    'method' => 'client_and_invoice_reference',
                    'reason' => 'Client matched and invoice reference appears in bank transaction.',
                ];
            }
        }

        return null;
    }

    private function collapseCandidates(Collection $candidates): Collection
    {
        return $candidates
            ->groupBy(fn ($candidate) => $candidate['client']->id)
            ->map(function (Collection $matches) {
                $best = $matches->sortByDesc('confidence')->first();

                return [
                    'client' => $best['client'],
                    'confidence' => min(
                        100,
                        $matches->sum('confidence')
                    ),
                ];
            })
            ->sortByDesc('confidence')
            ->values();
    }

    private function normalise(?string $value): string
    {
        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                strtolower(
                    preg_replace(
                        '/[^a-z0-9 ]/i',
                        ' ',
                        (string) $value
                    )
                )
            ) ?? ''
        );
    }
}
