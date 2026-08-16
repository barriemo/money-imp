<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Reconciliation;

use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidence;
use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;
use App\Models\AccountingInvoice;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;

class RecurringPaymentSequenceMatchService
{
    public function __construct(
        private CanonicalPaymentEvidenceService $payments
    ) {}

    public function preview(): array
    {
        return $this->evaluate(
            persist: false
        );
    }

    public function generate(): array
    {
        return $this->evaluate(
            persist: true
        );
    }

    private function evaluate(
        bool $persist
    ): array {
        $stats = [
            'groups_considered' => 0,
            'groups_matched' => 0,
            'payments_matched' => 0,
            'value' => 0.0,
            'groups_rejected' => 0,
        ];

        $groups =
            $this->unallocatedPayments()
                ->filter(
                    fn (CanonicalPaymentEvidence $payment) => $payment->clientId !== null
                )
                ->groupBy(
                    fn (CanonicalPaymentEvidence $payment) => implode(
                        '|',
                        [
                            $payment->clientId,
                            number_format(
                                $payment->amount,
                                2,
                                '.',
                                ''
                            ),
                        ]
                    )
                )
                ->filter(
                    fn (Collection $payments) => $payments->count() > 1
                );

        foreach ($groups as $payments) {
            $stats['groups_considered']++;

            $payments =
                $payments
                    ->sortBy('date')
                    ->values();

            $first =
                $payments->first();

            $invoices =
                $this->availableInvoices(
                    clientId: $first->clientId,
                    amount: $first->amount
                );

            if (
                $invoices->count()
                !== $payments->count()
            ) {
                $stats['groups_rejected']++;

                continue;
            }

            if (
                ! $this->sequenceIsChronologicallyValid(
                    $payments,
                    $invoices
                )
            ) {
                $stats['groups_rejected']++;

                continue;
            }

            foreach ($payments as $index => $payment) {
                $invoice =
                    $invoices[$index];

                if ($persist) {
                    PaymentAllocation::create([
                        'bank_transaction_id' => $payment->id,

                        'accounting_invoice_id' => $invoice->id,

                        'amount' => round(
                            $payment->amount,
                            2
                        ),

                        'status' => 'suggested',

                        'confidence' => 90,

                        'match_method' => 'canonical_recurring_sequence',

                        'reason' => 'Recurring same-client same-value payment sequence aligns one-to-one with the unresolved invoice sequence.',

                        'metadata' => [
                            'canonical_evidence_ids' => $payment->evidenceIds,

                            'sequence_index' => $index,

                            'sequence_size' => $payments->count(),
                        ],
                    ]);
                }

                $stats['payments_matched']++;

                $stats['value'] =
                    round(
                        $stats['value']
                        + $payment->amount,
                        2
                    );
            }

            $stats['groups_matched']++;
        }

        return $stats;
    }

    private function unallocatedPayments(): Collection
    {
        return $this->payments
            ->customerPayments()
            ->filter(
                fn (CanonicalPaymentEvidence $payment) => ! PaymentAllocation::query()
                    ->whereIn(
                        'bank_transaction_id',
                        $payment->evidenceIds
                    )
                    ->exists()
            )
            ->values();
    }

    private function availableInvoices(
        string $clientId,
        float $amount
    ): Collection {
        return AccountingInvoice::query()
            ->where(
                'client_id',
                $clientId
            )
            ->where(
                'gross_amount',
                '>',
                0
            )
            ->whereRaw(
                'ABS(gross_amount - ?) < 0.01',
                [
                    $amount,
                ]
            )
            ->whereDoesntHave(
                'paymentAllocations',
                fn ($query) => $query->whereIn(
                    'status',
                    [
                        'approved',
                        'imported',
                        'suggested',
                    ]
                )
            )
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
    }

    private function sequenceIsChronologicallyValid(
        Collection $payments,
        Collection $invoices
    ): bool {
        foreach ($payments as $index => $payment) {
            $invoice =
                $invoices[$index];

            if (
                $invoice->invoice_date
                && $invoice->invoice_date
                    ->toDateString() > $payment->date
            ) {
                return false;
            }
        }

        return true;
    }
}
