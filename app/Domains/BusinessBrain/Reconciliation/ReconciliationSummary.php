<?php

namespace App\Domains\BusinessBrain\Reconciliation;

class ReconciliationSummary
{
    public function __construct(
        public int $positiveTransactionCount,

        public float $positiveTransactionValue,

        public int $customerPaymentCount,

        public float $customerPaymentValue,

        public int $confirmedAllocationCount,

        public float $confirmedAllocationValue,

        public int $suggestedAllocationCount,

        public float $suggestedAllocationValue,

        public int $ignoredTransactionCount,

        public float $ignoredTransactionValue,

        public int $unmatchedTransactionCount,

        public float $unmatchedTransactionValue,

        public int $matchedInvoiceCount,

        public int $unmatchedInvoiceCount,

        public int $reconciliationCoverage
    ) {}
}
