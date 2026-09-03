<?php

namespace App\Domains\Reconciliation\Resolution;

use App\Domains\Accounting\Services\InvoiceBalanceService;
use App\Domains\Reconciliation\Services\ReconciliationEvidencePublisher;
use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReconciliationSuggestionResolutionService
{
    public function __construct(
        private readonly InvoiceBalanceService $balances,

        private readonly ReconciliationEvidencePublisher $evidence,
    ) {}

    /**
     * Candidate invoices are evidence for human review only.
     *
     * Date proximity is useful context for recurring invoices,
     * but it is not payment truth.
     *
     * @return Collection<int, array>
     */
    public function candidates(
        PaymentAllocation $allocation,
        int $limit = 5
    ): Collection {
        $allocation->loadMissing([
            'transaction.client',
            'invoice.client',
        ]);

        $transaction =
            $allocation->transaction;

        $currentInvoice =
            $allocation->invoice;

        if (
            ! $transaction
            || ! $currentInvoice
            || $transaction->client_id === null
        ) {
            return collect();
        }

        $amount =
            (float) $transaction->amount;

        $transactionDate =
            $transaction->transaction_date;

        return AccountingInvoice::query()
            ->where(
                'client_id',
                $transaction->client_id
            )
            ->whereBetween(
                'gross_amount',
                [
                    $amount - 0.009,
                    $amount + 0.009,
                ]
            )
            ->get()
            ->map(
                function (
                    AccountingInvoice $invoice
                ) use (
                    $transaction,
                    $transactionDate,
                    $currentInvoice,
                    $amount
                ): array {
                    $invoiceDate =
                        $invoice->invoice_date;

                    $days =
                        $invoiceDate
                        && $transactionDate
                            ? abs(
                                (float) $invoiceDate
                                    ->diffInDays(
                                        $transactionDate,
                                        false
                                    )
                            )
                            : 999999;

                    $balance =
                        round(
                            (float) $this
                                ->balances
                                ->outstanding(
                                    $invoice
                                ),
                            2
                        );

                    $historicalEligible =
                        $this->historicalEligible(
                            transaction: $transaction,

                            invoice: $invoice
                        );

                    $approvalEligible =
                        $balance > 0.009
                        && abs(
                            $balance
                            - $amount
                        ) < 0.01;

                    return [
                        'invoice' => $invoice,

                        'days_from_receipt' => $days,

                        'current_target' => $invoice->id
                            === $currentInvoice->id,

                        'invoice_balance' => $balance,

                        'historical_eligible' => $historicalEligible,

                        'approval_eligible' => $approvalEligible,

                        'explicit_invoice_reference' => $this->invoiceReferenceAppears(
                            transaction: $transaction,

                            invoice: $invoice
                        ),
                    ];
                }
            )
            ->sort(
                function (
                    array $a,
                    array $b
                ): int {
                    $days =
                        $a[
                            'days_from_receipt'
                        ]
                        <=>
                        $b[
                            'days_from_receipt'
                        ];

                    if ($days !== 0) {
                        return $days;
                    }

                    return (
                        $b[
                            'invoice'
                        ]
                            ->invoice_date
                            ?->timestamp
                        ?? 0
                    )
                    <=>
                    (
                        $a[
                            'invoice'
                        ]
                            ->invoice_date
                            ?->timestamp
                        ?? 0
                    );
                }
            )
            ->take(
                max(
                    1,
                    min(
                        20,
                        $limit
                    )
                )
            )
            ->values();
    }

    public function historicalClassification(
        PaymentAllocation $allocation
    ): array {
        $allocation->loadMissing([
            'transaction',
            'invoice',
        ]);

        $transaction =
            $allocation->transaction;

        $invoice =
            $allocation->invoice;

        if (
            ! $transaction
            || ! $invoice
        ) {
            return [
                'classification' => 'historical_review_required',

                'explicit_reference' => false,

                'amount_matches' => false,

                'source_paid' => false,
            ];
        }

        $explicitReference =
            $this->invoiceReferenceAppears(
                transaction: $transaction,

                invoice: $invoice
            );

        $amountMatches =
            $this->amountMatchesGross(
                transaction: $transaction,

                invoice: $invoice
            );

        $sourcePaid =
            $this->sourceMarksPaid(
                $invoice
            );

        return [
            'classification' => $explicitReference
                && $amountMatches
                && $sourcePaid
                    ? 'historical_corroboration_candidate'
                    : 'historical_review_required',

            'explicit_reference' => $explicitReference,

            'amount_matches' => $amountMatches,

            'source_paid' => $sourcePaid,
        ];
    }

    public function resolveHistorical(
        PaymentAllocation $allocation,
        AccountingInvoice $invoice,
        string $userId
    ): PaymentAllocation {
        $resolved =
            DB::transaction(
                function () use (
                    $allocation,
                    $invoice,
                    $userId
                ): PaymentAllocation {
                    $allocation =
                        PaymentAllocation::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $allocation->id
                            );

                    $this->assertSuggested(
                        $allocation
                    );

                    $transaction =
                        BankTransaction::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $allocation
                                    ->bank_transaction_id
                            );

                    $invoice =
                        AccountingInvoice::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $invoice->id
                            );

                    $this->assertSameClient(
                        transaction: $transaction,

                        invoice: $invoice
                    );

                    if (
                        ! $this->historicalEligible(
                            transaction: $transaction,

                            invoice: $invoice
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'resolution' => 'Historical corroboration requires an exact same-client receipt against an invoice the accounting source already marks paid.',
                        ]);
                    }

                    $explicitReference =
                        $this->invoiceReferenceAppears(
                            transaction: $transaction,

                            invoice: $invoice
                        );

                    $originalInvoiceId =
                        $allocation
                            ->accounting_invoice_id;

                    $target =
                        $allocation;

                    if (
                        $invoice->id
                        !== $originalInvoiceId
                    ) {
                        $existing =
                            PaymentAllocation::query()
                                ->lockForUpdate()
                                ->where(
                                    'bank_transaction_id',
                                    $transaction->id
                                )
                                ->where(
                                    'accounting_invoice_id',
                                    $invoice->id
                                )
                                ->first();

                        if (
                            $existing
                            && in_array(
                                $existing->status,
                                [
                                    'approved',
                                    'imported',
                                ],
                                true
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'resolution' => 'This receipt already has canonical payment evidence against the selected invoice.',
                            ]);
                        }

                        $this->supersede(
                            allocation: $allocation,

                            userId: $userId,

                            resolution: 'historical_corroboration',

                            targetInvoiceId: $invoice->id
                        );

                        $target =
                            $existing
                            ?? new PaymentAllocation([
                                'bank_transaction_id' => $transaction->id,

                                'accounting_invoice_id' => $invoice->id,
                            ]);
                    }

                    $metadata =
                        $target->metadata
                        ?? [];

                    $metadata[
                        'historical_corroboration'
                    ] = [
                        'recorded_at' => now()->toIso8601String(),

                        'recorded_by' => $userId,

                        'original_suggestion_id' => $allocation->id,

                        'original_invoice_id' => $originalInvoiceId,

                        'source_invoice_status' => $invoice->status,

                        'source_paid_amount' => (float) $invoice
                            ->paid_amount,

                        'source_outstanding_amount' => (float) $invoice
                            ->outstanding_amount,

                        'explicit_invoice_reference' => $explicitReference,

                        'evidence_basis' => $explicitReference
                                ? 'bank_invoice_reference_amount_source_paid'
                                : 'human_selected_same_client_amount_source_paid',
                    ];

                    $target->fill([
                        'amount' => (float) $transaction
                            ->amount,

                        'status' => PaymentAllocation::STATUS_HISTORICAL_CORROBORATION,

                        'confidence' => $target->confidence
                            ?? $allocation
                                ->confidence,

                        'approved_by' => null,

                        'approved_at' => null,

                        'match_method' => 'human_historical_corroboration',

                        'reason' => 'Human recorded bank receipt as historical corroboration of an invoice the accounting source already marks paid.',

                        'metadata' => $metadata,
                    ]);

                    $target->save();

                    return $target->fresh([
                        'transaction',
                        'invoice',
                    ]);
                }
            );

        $this->evidence
            ->publish(
                type: 'payment_allocation_historical_corroboration_recorded',

                clientId: $resolved
                    ->invoice
                    ?->client_id
                    ?? $resolved
                        ->transaction
                        ?->client_id,

                metadata: [
                    'allocation_id' => $resolved->id,

                    'bank_transaction_id' => $resolved
                        ->bank_transaction_id,

                    'accounting_invoice_id' => $resolved
                        ->accounting_invoice_id,

                    'status' => $resolved->status,

                    'amount' => (float) $resolved
                        ->amount,

                    'canonical_invoice_allocation' => false,
                ]
            );

        return $resolved;
    }

    public function resolveApproved(
        PaymentAllocation $allocation,
        AccountingInvoice $invoice,
        string $userId
    ): PaymentAllocation {
        $resolved =
            DB::transaction(
                function () use (
                    $allocation,
                    $invoice,
                    $userId
                ): PaymentAllocation {
                    $allocation =
                        PaymentAllocation::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $allocation->id
                            );

                    $this->assertSuggested(
                        $allocation
                    );

                    $transaction =
                        BankTransaction::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $allocation
                                    ->bank_transaction_id
                            );

                    $invoice =
                        AccountingInvoice::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $invoice->id
                            );

                    $this->assertSameClient(
                        transaction: $transaction,

                        invoice: $invoice
                    );

                    if (
                        ! $this->amountMatchesGross(
                            transaction: $transaction,

                            invoice: $invoice
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'resolution' => 'Recurring-payment resolution requires the selected invoice value to match the receipt exactly.',
                        ]);
                    }

                    $invoiceBalance =
                        (float) $this
                            ->balances
                            ->outstanding(
                                $invoice
                            );

                    if (
                        $invoiceBalance <= 0
                        || abs(
                            $invoiceBalance
                            - (float) $transaction
                                ->amount
                        ) >= 0.01
                    ) {
                        throw ValidationException::withMessages([
                            'resolution' => 'The selected invoice does not have an exact allocatable balance for this receipt.',
                        ]);
                    }

                    $transactionAllocated =
                        (float) $transaction
                            ->paymentAllocations()
                            ->whereIn(
                                'status',
                                [
                                    'approved',
                                    'imported',
                                ]
                            )
                            ->where(
                                'id',
                                '!=',
                                $allocation->id
                            )
                            ->sum(
                                'amount'
                            );

                    $paymentAvailable =
                        max(
                            0,
                            (float) $transaction
                                ->amount
                            - $transactionAllocated
                        );

                    if (
                        abs(
                            $paymentAvailable
                            - (float) $transaction
                                ->amount
                        ) >= 0.01
                    ) {
                        throw ValidationException::withMessages([
                            'resolution' => 'The receipt already has canonical allocated value and cannot be retargeted as a full recurring payment.',
                        ]);
                    }

                    $originalInvoiceId =
                        $allocation
                            ->accounting_invoice_id;

                    $target =
                        $allocation;

                    if (
                        $invoice->id
                        !== $originalInvoiceId
                    ) {
                        $existing =
                            PaymentAllocation::query()
                                ->lockForUpdate()
                                ->where(
                                    'bank_transaction_id',
                                    $transaction->id
                                )
                                ->where(
                                    'accounting_invoice_id',
                                    $invoice->id
                                )
                                ->first();

                        if (
                            $existing
                            && in_array(
                                $existing->status,
                                [
                                    'approved',
                                    'imported',
                                ],
                                true
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'resolution' => 'This receipt already has canonical payment evidence against the selected invoice.',
                            ]);
                        }

                        $this->supersede(
                            allocation: $allocation,

                            userId: $userId,

                            resolution: 'approved_retarget',

                            targetInvoiceId: $invoice->id
                        );

                        $target =
                            $existing
                            ?? new PaymentAllocation([
                                'bank_transaction_id' => $transaction->id,

                                'accounting_invoice_id' => $invoice->id,
                            ]);
                    }

                    $metadata =
                        $target->metadata
                        ?? [];

                    $metadata[
                        'recurring_resolution'
                    ] = [
                        'resolved_at' => now()->toIso8601String(),

                        'resolved_by' => $userId,

                        'original_suggestion_id' => $allocation->id,

                        'original_invoice_id' => $originalInvoiceId,

                        'resolution' => 'approved_exact_invoice',
                    ];

                    $target->fill([
                        'amount' => (float) $transaction
                            ->amount,

                        'status' => 'approved',

                        'confidence' => 100,

                        'approved_by' => $userId,

                        'approved_at' => now(),

                        'match_method' => 'manual_recurring_resolution',

                        'reason' => 'Human retargeted recurring receipt to an exact same-client outstanding invoice.',

                        'metadata' => $metadata,
                    ]);

                    $target->save();

                    $totalAllocated =
                        (float) $transaction
                            ->paymentAllocations()
                            ->whereIn(
                                'status',
                                [
                                    'approved',
                                    'imported',
                                ]
                            )
                            ->sum(
                                'amount'
                            );

                    $transaction->update([
                        'match_status' => $totalAllocated + 0.01
                            >= (float) $transaction
                                ->amount
                                ? 'reconciled'
                                : 'partially_allocated',

                        'matched_by' => $userId,

                        'matched_at' => now(),
                    ]);

                    return $target->fresh([
                        'transaction',
                        'invoice',
                    ]);
                }
            );

        $this->evidence
            ->publish(
                type: 'payment_allocation_approved',

                clientId: $resolved
                    ->invoice
                    ?->client_id
                    ?? $resolved
                        ->transaction
                        ?->client_id,

                metadata: [
                    'allocation_id' => $resolved->id,

                    'bank_transaction_id' => $resolved
                        ->bank_transaction_id,

                    'accounting_invoice_id' => $resolved
                        ->accounting_invoice_id,

                    'status' => $resolved->status,

                    'amount' => (float) $resolved
                        ->amount,

                    'resolution' => 'manual_recurring_resolution',
                ]
            );

        return $resolved;
    }

    private function assertSuggested(
        PaymentAllocation $allocation
    ): void {
        if (
            $allocation->status
            !== 'suggested'
        ) {
            throw ValidationException::withMessages([
                'resolution' => 'This reconciliation suggestion is no longer awaiting resolution.',
            ]);
        }
    }

    private function assertSameClient(
        BankTransaction $transaction,
        AccountingInvoice $invoice
    ): void {
        if (
            $transaction->client_id === null
            || $transaction->client_id
                !== $invoice->client_id
        ) {
            throw ValidationException::withMessages([
                'resolution' => 'The receipt and selected invoice must belong to the same client.',
            ]);
        }
    }

    private function historicalEligible(
        BankTransaction $transaction,
        AccountingInvoice $invoice
    ): bool {
        return $this->amountMatchesGross(
            transaction: $transaction,

            invoice: $invoice
        )
        && $this->sourceMarksPaid(
            $invoice
        );
    }

    private function sourceMarksPaid(
        AccountingInvoice $invoice
    ): bool {
        return (float) $invoice
            ->outstanding_amount
            <= 0.009
        && (
            $invoice->status === 'paid'
            || (float) $invoice
                ->paid_amount
                >= max(
                    0,
                    (float) $invoice
                        ->gross_amount
                    - 0.009
                )
        );
    }

    private function amountMatchesGross(
        BankTransaction $transaction,
        AccountingInvoice $invoice
    ): bool {
        return abs(
            (float) $transaction
                ->amount
            - (float) $invoice
                ->gross_amount
        ) < 0.01;
    }

    private function invoiceReferenceAppears(
        BankTransaction $transaction,
        AccountingInvoice $invoice
    ): bool {
        $invoiceNumber =
            $this->normalise(
                $invoice->invoice_number
            );

        if ($invoiceNumber === '') {
            return false;
        }

        $text =
            $this->normalise(
                trim(
                    implode(
                        ' ',
                        array_filter([
                            $transaction
                                ->description,

                            $transaction
                                ->reference,

                            $transaction
                                ->metadata[
                                    'freeagent_full_description'
                                ]
                            ?? null,
                        ])
                    )
                )
            );

        return str_contains(
            $text,
            $invoiceNumber
        );
    }

    private function supersede(
        PaymentAllocation $allocation,
        string $userId,
        string $resolution,
        string $targetInvoiceId
    ): void {
        $metadata =
            $allocation->metadata
            ?? [];

        $metadata[
            'resolution_superseded'
        ] = [
            'at' => now()->toIso8601String(),

            'by' => $userId,

            'resolution' => $resolution,

            'target_invoice_id' => $targetInvoiceId,
        ];

        $allocation->update([
            'status' => 'rejected',

            'metadata' => $metadata,
        ]);
    }

    private function normalise(
        ?string $value
    ): string {
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
