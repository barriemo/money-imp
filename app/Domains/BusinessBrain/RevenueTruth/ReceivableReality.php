<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

class ReceivableReality
{
    public function __construct(
        public float $reportedOutstanding,

        public int $invoiceCount,

        public int $overdueInvoiceCount,

        public int $confidence,

        public array $priorityInvoices,

        public float $ledgerOutstanding = 0,

        public float $writtenOffAmount = 0,

        public float $draftAmount = 0
    ) {}
}
