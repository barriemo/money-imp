<?php

namespace App\Domains\BusinessBrain\PaymentTruth;

class PaymentTruthSummary
{
    public function __construct(
        public int $invoiceCount,

        public float $totalInvoiced,

        public float $bankConfirmedReceived,

        public float $suggestedReceived,

        public float $provenOutstanding,

        public float $accountingReportedPaid,

        public float $accountingReportedOutstanding,

        public float $accountingConflictValue,

        public int $paidInvoiceCount,

        public int $partPaidInvoiceCount,

        public int $ambiguousInvoiceCount,

        public int $unpaidInvoiceCount,

        public int $accountingConflictCount,

        public float $unallocatedIncomingCash,

        public int $confidence
    ) {}
}
