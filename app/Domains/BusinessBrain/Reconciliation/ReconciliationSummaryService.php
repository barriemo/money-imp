<?php

namespace App\Domains\BusinessBrain\Reconciliation;

use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\PaymentAllocation;

class ReconciliationSummaryService
{
    public function current(): ReconciliationSummary
    {
        $positive =
            BankTransaction::query()
                ->where(
                    'amount',
                    '>',
                    0
                );

        $customerPayments =
            BankTransaction::query()
                ->where(
                    'amount',
                    '>',
                    0
                )
                ->where(
                    'transaction_type',
                    'customer_payment'
                );

        $ignored =
            BankTransaction::query()
                ->where(
                    'amount',
                    '>',
                    0
                )
                ->where(
                    'match_status',
                    'ignored'
                );

        $unmatched =
            BankTransaction::query()
                ->where(
                    'amount',
                    '>',
                    0
                )
                ->where(
                    'match_status',
                    'unmatched'
                );

        $confirmedAllocations =
            PaymentAllocation::query()
                ->whereIn(
                    'status',
                    [
                        'approved',
                        'imported',
                    ]
                );

        $suggestedAllocations =
            PaymentAllocation::query()
                ->where(
                    'status',
                    'suggested'
                );

        $matchedInvoiceIds =
            PaymentAllocation::query()
                ->whereIn(
                    'status',
                    [
                        'approved',
                        'imported',
                        'suggested',
                    ]
                )
                ->pluck(
                    'accounting_invoice_id'
                )
                ->unique();

        $invoiceCount =
            AccountingInvoice::query()
                ->where(
                    'gross_amount',
                    '>',
                    0
                )
                ->count();

        $matchedInvoiceCount =
            $matchedInvoiceIds->count();

        $customerPaymentCount =
            (clone $customerPayments)
                ->count();

        $confirmedCustomerTransactions =
            BankTransaction::query()
                ->where(
                    'amount',
                    '>',
                    0
                )
                ->where(
                    'transaction_type',
                    'customer_payment'
                )
                ->whereHas(
                    'paymentAllocations',
                    fn ($query) => $query->whereIn(
                        'status',
                        [
                            'approved',
                            'imported',
                        ]
                    )
                )
                ->count();

        $coverage =
            $customerPaymentCount > 0
                ? (int) round(
                    (
                        $confirmedCustomerTransactions
                        / $customerPaymentCount
                    ) * 100
                )
                : 100;

        return new ReconciliationSummary(
            positiveTransactionCount: (clone $positive)
                ->count(),

            positiveTransactionValue: $this->money(
                (clone $positive)
                    ->sum(
                        'amount'
                    )
            ),

            customerPaymentCount: $customerPaymentCount,

            customerPaymentValue: $this->money(
                (clone $customerPayments)
                    ->sum(
                        'amount'
                    )
            ),

            confirmedAllocationCount: (clone $confirmedAllocations)
                ->count(),

            confirmedAllocationValue: $this->money(
                (clone $confirmedAllocations)
                    ->sum(
                        'amount'
                    )
            ),

            suggestedAllocationCount: (clone $suggestedAllocations)
                ->count(),

            suggestedAllocationValue: $this->money(
                (clone $suggestedAllocations)
                    ->sum(
                        'amount'
                    )
            ),

            ignoredTransactionCount: (clone $ignored)
                ->count(),

            ignoredTransactionValue: $this->money(
                (clone $ignored)
                    ->sum(
                        'amount'
                    )
            ),

            unmatchedTransactionCount: (clone $unmatched)
                ->count(),

            unmatchedTransactionValue: $this->money(
                (clone $unmatched)
                    ->sum(
                        'amount'
                    )
            ),

            matchedInvoiceCount: $matchedInvoiceCount,

            unmatchedInvoiceCount: max(
                0,
                $invoiceCount
                - $matchedInvoiceCount
            ),

            reconciliationCoverage: $coverage
        );
    }

    private function money(
        float|int|string $value
    ): float {
        return round(
            (float) $value,
            2
        );
    }
}
