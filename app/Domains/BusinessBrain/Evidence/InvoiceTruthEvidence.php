<?php

namespace App\Domains\BusinessBrain\Evidence;

class InvoiceTruthEvidence
{
    public function __construct(
        public string $invoiceId,

        public string $invoiceNumber,

        public string $accountingStatus,

        public float $grossAmount,

        public float $paidAmount,

        public float $outstandingAmount,

        public int $paymentAllocationCount,

        public int $approvedPaymentAllocationCount,

        public float $approvedPaymentValue,

        public bool $accountingSaysPaid,

        public bool $hasBankEvidence,

        public bool $hasEvidenceConflict,

        public int $confidence,

        public array $reasons
    ) {}
}
