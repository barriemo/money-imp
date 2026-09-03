<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Investigation;

use App\Domains\BusinessBrain\PaymentTruth\Ledger\ClientLedgerAnalysisService;
use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ExternalRecord;
use App\Models\PaymentIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class ClientPaymentEvidenceSearchService
{
    public function __construct(
        private readonly ClientLedgerAnalysisService $ledger,
    ) {}

    public function search(
        string $clientId
    ): ClientPaymentEvidenceSearchResult {
        $client =
            Client::query()
                ->findOrFail(
                    $clientId
                );

        $invoices =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'gross_amount',
                    '>',
                    0
                )
                ->orderBy(
                    'invoice_date'
                )
                ->orderBy(
                    'id'
                )
                ->get();

        $position =
            $this->ledger
                ->current()
                ->firstWhere(
                    'clientId',
                    $client->id
                );

        $firstInvoiceAt =
            $invoices
                ->whereNotNull(
                    'invoice_date'
                )
                ->first()
                ?->invoice_date
                ?->toDateString();

        $lastInvoiceAt =
            $invoices
                ->whereNotNull(
                    'invoice_date'
                )
                ->last()
                ?->invoice_date
                ?->toDateString();

        $bankFirstTransactionAt =
            BankTransaction::query()
                ->min(
                    'transaction_date'
                );

        $bankLastTransactionAt =
            BankTransaction::query()
                ->max(
                    'transaction_date'
                );

        $bankDateSpanCoversInvoices =
            $this->dateSpanCovers(
                firstInvoiceAt: $firstInvoiceAt,

                lastInvoiceAt: $lastInvoiceAt,

                bankFirstTransactionAt: $bankFirstTransactionAt,

                bankLastTransactionAt: $bankLastTransactionAt
            );

        $identities =
            PaymentIdentity::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'direction',
                    'incoming'
                )
                ->get();

        $highConfidenceIdentities =
            $identities
                ->filter(
                    fn (PaymentIdentity $identity) => (float) $identity->confidence
                        >= 80
                )
                ->values();

        $aliases =
            $this->aliases(
                client: $client,

                identities: $highConfidenceIdentities
            );

        $transactions =
            BankTransaction::query()
                ->where(
                    'amount',
                    '>',
                    0
                )
                ->with([
                    'client',
                    'explanations',
                    'paymentAllocations',
                    'supplierPaymentAllocations',
                ])
                ->orderBy(
                    'transaction_date'
                )
                ->orderBy(
                    'id'
                )
                ->get();

        $supportedCandidates = [];

        $directAliasHits = [];
        $paymentIdentityHits = [];
        $explicitReferenceHits = [];

        foreach (
            $transactions as $transaction
        ) {
            if (
                ! $this->isInsideInvoiceEvidencePeriod(
                    transaction: $transaction,

                    firstInvoiceAt: $firstInvoiceAt
                )
            ) {
                continue;
            }

            /*
             * Already attributed canonical cash is not a
             * "hidden payment candidate".
             */
            if (
                $transaction->client_id
                === $client->id
            ) {
                continue;
            }

            if (
                ! $this->sourceStillOpen(
                    $transaction
                )
            ) {
                continue;
            }

            $text =
                $this->transactionText(
                    $transaction
                );

            foreach ($aliases as $alias) {
                if (
                    $alias[
                        'normalised'
                    ] === ''
                ) {
                    continue;
                }

                if (
                    str_contains(
                        $this->normaliseText(
                            $text
                        ),
                        $alias[
                            'normalised'
                        ]
                    )
                ) {
                    $directAliasHits[
                        $transaction->id
                    ] =
                        true;

                    $this->candidate(
                        candidates: $supportedCandidates,

                        transaction: $transaction,

                        reason: 'direct_alias:'
                            .$alias[
                                'display'
                            ]
                    );

                    break;
                }
            }

            foreach (
                $highConfidenceIdentities as $identity
            ) {
                if (
                    ! $this->matchesPaymentIdentity(
                        transaction: $transaction,

                        identity: $identity
                    )
                ) {
                    continue;
                }

                $paymentIdentityHits[
                    $transaction->id
                ] =
                    true;

                $this->candidate(
                    candidates: $supportedCandidates,

                    transaction: $transaction,

                    reason: 'payment_identity:'
                        .$identity->identity_type
                );

                break;
            }

            foreach ($invoices as $invoice) {
                if (
                    ! $invoice->invoice_date
                    || ! $transaction->transaction_date
                    || $transaction
                        ->transaction_date
                        ->lt(
                            $invoice->invoice_date
                        )
                ) {
                    continue;
                }

                $reference =
                    trim(
                        (string) $invoice->invoice_number
                    );

                if (
                    $reference === ''
                    || strlen(
                        $reference
                    ) < 3
                ) {
                    continue;
                }

                if (
                    ! $this->hasExplicitInvoiceReference(
                        text: $text,

                        reference: $reference
                    )
                ) {
                    continue;
                }

                $explicitReferenceHits[
                    $transaction->id
                    .'|'
                    .$reference
                ] =
                    true;

                $this->candidate(
                    candidates: $supportedCandidates,

                    transaction: $transaction,

                    reason: 'explicit_invoice_reference:'
                        .$reference
                );
            }
        }

        [
            $exactAmountCoincidenceCount,
            $namedOtherExactAmountCoincidenceCount,
            $anonymousExactAmountCoincidenceCount,
        ] =
            $this->exactAmountCoincidences(
                invoices: $invoices,

                transactions: $transactions,

                clientId: $client->id
            );

        $state =
            $this->state(
                invoiceCount: $invoices->count(),

                bankFirstTransactionAt: $bankFirstTransactionAt,

                bankLastTransactionAt: $bankLastTransactionAt,

                bankDateSpanCoversInvoices: $bankDateSpanCoversInvoices,

                supportedCandidateCount: count(
                    $supportedCandidates
                ),

                anonymousExactAmountCoincidenceCount: $anonymousExactAmountCoincidenceCount
            );

        return new ClientPaymentEvidenceSearchResult(
            clientId: $client->id,

            clientName: $client->name,

            state: $state,

            invoiceCount: $invoices->count(),

            accountingPaid: round(
                (float) $invoices
                    ->sum(
                        'paid_amount'
                    ),
                2
            ),

            accountingOutstanding: round(
                (float) $invoices
                    ->sum(
                        'outstanding_amount'
                    ),
                2
            ),

            canonicalCash: round(
                (float) (
                    $position
                        ?->cashReceived
                    ?? 0
                ),
                2
            ),

            firstInvoiceAt: $firstInvoiceAt,

            lastInvoiceAt: $lastInvoiceAt,

            bankFirstTransactionAt: $bankFirstTransactionAt
                    ? CarbonImmutable::parse(
                        $bankFirstTransactionAt
                    )->toDateString()
                    : null,

            bankLastTransactionAt: $bankLastTransactionAt
                    ? CarbonImmutable::parse(
                        $bankLastTransactionAt
                    )->toDateString()
                    : null,

            bankDateSpanCoversInvoices: $bankDateSpanCoversInvoices,

            paymentIdentityCount: $identities->count(),

            highConfidencePaymentIdentityCount: $highConfidenceIdentities->count(),

            aliases: array_values(
                array_map(
                    fn (array $alias) => $alias[
                            'display'
                        ],
                    $aliases
                )
            ),

            directAliasHitCount: count(
                $directAliasHits
            ),

            paymentIdentityHitCount: count(
                $paymentIdentityHits
            ),

            explicitInvoiceReferenceHitCount: count(
                $explicitReferenceHits
            ),

            exactAmountCoincidenceCount: $exactAmountCoincidenceCount,

            namedOtherExactAmountCoincidenceCount: $namedOtherExactAmountCoincidenceCount,

            anonymousExactAmountCoincidenceCount: $anonymousExactAmountCoincidenceCount,

            supportedCandidates: array_values(
                $supportedCandidates
            ),

            truthBoundary: 'A payment evidence search can establish that no supported receipt candidate was found in the available evidence. It cannot prove that no payment occurred. Amount coincidence alone is not payment identity, and bank date-span coverage does not prove that every source statement or payer identity is complete.'
        );
    }

    private function aliases(
        Client $client,
        Collection $identities
    ): array {
        $contactRecord =
            ExternalRecord::query()
                ->where(
                    'recordable_type',
                    Client::class
                )
                ->where(
                    'recordable_id',
                    $client->id
                )
                ->where(
                    'resource_type',
                    'contact'
                )
                ->first();

        $contact =
            $contactRecord
                ?->payload
            ?? [];

        $person =
            trim(
                implode(
                    ' ',
                    array_filter([
                        $contact[
                            'first_name'
                        ] ?? null,

                        $contact[
                            'last_name'
                        ] ?? null,
                    ])
                )
            );

        $values =
            collect([
                $client->name,
                $client->legal_name,

                $contact[
                    'organisation_name'
                ] ?? null,

                $person !== ''
                    ? $person
                    : null,
            ]);

        foreach ($identities as $identity) {
            if (
                ! in_array(
                    $identity->identity_type,
                    [
                        'reference',
                        'counterparty_name',
                        'composite',
                    ],
                    true
                )
            ) {
                continue;
            }

            $values->push(
                $identity->identity_value
            );
        }

        return $values
            ->filter(
                fn ($value) => is_string(
                    $value
                )
                    && strlen(
                        trim(
                            $value
                        )
                    ) >= 4
            )
            ->map(
                fn (string $value) => [
                    'display' => trim(
                        $value
                    ),

                    'normalised' => $this->normaliseText(
                        $value
                    ),
                ]
            )
            ->unique(
                'normalised'
            )
            ->values()
            ->all();
    }

    private function matchesPaymentIdentity(
        BankTransaction $transaction,
        PaymentIdentity $identity
    ): bool {
        $expected =
            $this->compact(
                $identity->normalized_value
                ?: $identity->identity_value
            );

        if ($expected === '') {
            return false;
        }

        $actual =
            match (
                $identity->identity_type
            ) {
                'counterparty_name' => $transaction->counterparty_name,

                'reference' => $transaction->reference,

                'bank_account' => $transaction->counterparty_account,

                default => null,
            };

        if (
            $actual === null
            || trim(
                (string) $actual
            ) === ''
        ) {
            return false;
        }

        return $this->compact(
            (string) $actual
        ) === $expected;
    }

    private function hasExplicitInvoiceReference(
        string $text,
        string $reference
    ): bool {
        if (
            ctype_digit(
                $reference
            )
        ) {
            $number =
                ltrim(
                    $reference,
                    '0'
                );

            if ($number === '') {
                $number = '0';
            }

            $escaped =
                preg_quote(
                    $number,
                    '/'
                );

            return preg_match(
                '/(?:^|[^a-z0-9])'
                .'(?:invoice|inv)'
                .'[\s#:\-\/]*'
                .'0*'
                .$escaped
                .'(?:[^a-z0-9]|$)/i',
                $text
            ) === 1;
        }

        $escaped =
            preg_quote(
                $reference,
                '/'
            );

        return preg_match(
            '/(?:^|[^a-z0-9])'
            .'(?:invoice|inv)'
            .'[\s#:\-\/]*'
            .$escaped
            .'(?:[^a-z0-9]|$)/i',
            $text
        ) === 1;
    }

    private function exactAmountCoincidences(
        Collection $invoices,
        Collection $transactions,
        string $clientId
    ): array {
        $amountEvidence = [];

        foreach ($invoices as $invoice) {
            if (
                ! $invoice->invoice_date
            ) {
                continue;
            }

            $key =
                number_format(
                    (float) $invoice->gross_amount,
                    2,
                    '.',
                    ''
                );

            $date =
                $invoice
                    ->invoice_date
                    ->toDateString();

            if (
                ! isset(
                    $amountEvidence[
                        $key
                    ]
                )
                || $date
                    < $amountEvidence[
                        $key
                    ]
            ) {
                $amountEvidence[
                    $key
                ] =
                    $date;
            }
        }

        $all = [];
        $named = [];
        $anonymous = [];

        foreach ($transactions as $transaction) {
            if (
                $transaction->client_id
                === $clientId
            ) {
                continue;
            }

            if (
                ! $this->sourceStillOpen(
                    $transaction
                )
            ) {
                continue;
            }

            if (
                $transaction
                    ->paymentAllocations
                    ->isNotEmpty()
                || $transaction
                    ->supplierPaymentAllocations
                    ->isNotEmpty()
            ) {
                continue;
            }

            $key =
                number_format(
                    (float) $transaction->amount,
                    2,
                    '.',
                    ''
                );

            $firstInvoiceAt =
                $amountEvidence[
                    $key
                ] ?? null;

            if (
                $firstInvoiceAt === null
                || ! $transaction->transaction_date
                || $transaction
                    ->transaction_date
                    ->toDateString()
                    < $firstInvoiceAt
            ) {
                continue;
            }

            $all[
                $transaction->id
            ] =
                true;

            $description =
                preg_replace(
                    '/[^a-z0-9]+/i',
                    '',
                    (string) $transaction->description
                )
                ?? '';

            if ($description === '') {
                $anonymous[
                    $transaction->id
                ] =
                    true;
            } else {
                $named[
                    $transaction->id
                ] =
                    true;
            }
        }

        return [
            count(
                $all
            ),

            count(
                $named
            ),

            count(
                $anonymous
            ),
        ];
    }

    private function candidate(
        array &$candidates,
        BankTransaction $transaction,
        string $reason
    ): void {
        if (
            ! isset(
                $candidates[
                    $transaction->id
                ]
            )
        ) {
            $candidates[
                $transaction->id
            ] = [
                'transaction_id' => $transaction->id,

                'transaction_date' => $transaction->transaction_date
                    ?->toDateString(),

                'amount' => (float) $transaction->amount,

                'description' => $transaction->description,

                'counterparty_name' => $transaction->counterparty_name,

                'reference' => $transaction->reference,

                'mapped_client_id' => $transaction->client_id,

                'mapped_client_name' => $transaction
                    ->client
                    ?->name,

                'match_status' => $transaction->match_status,

                'source_type' => $transaction->source_type,

                'source_unexplained_amount' => $transaction->metadata[
                        'freeagent_unexplained_amount'
                    ] ?? null,

                'reasons' => [],
            ];
        }

        if (
            ! in_array(
                $reason,
                $candidates[
                    $transaction->id
                ][
                    'reasons'
                ],
                true
            )
        ) {
            $candidates[
                $transaction->id
            ][
                'reasons'
            ][] =
                $reason;
        }
    }

    private function transactionText(
        BankTransaction $transaction
    ): string {
        return implode(
            ' | ',
            array_filter([
                $transaction->description,

                $transaction->reference,

                $transaction->counterparty_name,

                $transaction->metadata[
                    'freeagent_full_description'
                ] ?? null,

                $transaction
                    ->explanations
                    ->pluck(
                        'description'
                    )
                    ->filter()
                    ->implode(
                        ' | '
                    ),
            ])
        );
    }

    private function sourceStillOpen(
        BankTransaction $transaction
    ): bool {
        $unexplained =
            $transaction->metadata[
                'freeagent_unexplained_amount'
            ] ?? null;

        if ($unexplained !== null) {
            return (float) $unexplained
                > 0.009;
        }

        return ! in_array(
            $transaction->match_status,
            [
                'reconciled',
                'ignored',
            ],
            true
        );
    }

    private function isInsideInvoiceEvidencePeriod(
        BankTransaction $transaction,
        ?string $firstInvoiceAt
    ): bool {
        if (
            $firstInvoiceAt === null
            || ! $transaction->transaction_date
        ) {
            return true;
        }

        return $transaction
            ->transaction_date
            ->toDateString()
            >= $firstInvoiceAt;
    }

    private function dateSpanCovers(
        ?string $firstInvoiceAt,
        ?string $lastInvoiceAt,
        ?string $bankFirstTransactionAt,
        ?string $bankLastTransactionAt
    ): bool {
        if (
            $firstInvoiceAt === null
            || $lastInvoiceAt === null
            || $bankFirstTransactionAt === null
            || $bankLastTransactionAt === null
        ) {
            return false;
        }

        return CarbonImmutable::parse(
            $bankFirstTransactionAt
        )
            ->toDateString()
            <= $firstInvoiceAt

            && CarbonImmutable::parse(
                $bankLastTransactionAt
            )
                ->toDateString()
                >= $lastInvoiceAt;
    }

    private function state(
        int $invoiceCount,
        ?string $bankFirstTransactionAt,
        ?string $bankLastTransactionAt,
        bool $bankDateSpanCoversInvoices,
        int $supportedCandidateCount,
        int $anonymousExactAmountCoincidenceCount
    ): string {
        if ($invoiceCount === 0) {
            return 'no_invoice_evidence';
        }

        if (
            $bankFirstTransactionAt === null
            || $bankLastTransactionAt === null
        ) {
            return 'bank_evidence_missing';
        }

        if (
            ! $bankDateSpanCoversInvoices
        ) {
            return 'bank_date_span_incomplete';
        }

        if (
            $supportedCandidateCount > 0
        ) {
            return 'supported_payment_candidate_found';
        }

        if (
            $anonymousExactAmountCoincidenceCount > 0
        ) {
            return 'weak_unidentified_exact_amount_candidates';
        }

        return 'no_supported_payment_candidate_found';
    }

    private function normaliseText(
        string $value
    ): string {
        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                preg_replace(
                    '/[^a-z0-9]+/',
                    ' ',
                    strtolower(
                        $value
                    )
                ) ?? ''
            ) ?? ''
        );
    }

    private function compact(
        string $value
    ): string {
        return preg_replace(
            '/[^a-z0-9]+/',
            '',
            strtolower(
                $value
            )
        ) ?? '';
    }
}
