<?php

namespace App\Domains\BusinessBrain\Evidence;

class ClientPaymentEvidenceSummary
{
    public function __construct(
        public string $clientId,

        public string $client,

        public int $invoiceCount,

        public int $paidInvoiceCount,

        public int $approvedPaymentAllocationCount,

        public int $suggestedPaymentAllocationCount,

        public int $paidInvoicesWithoutApprovedEvidence,

        public float $approvedPaymentValue,

        public int $confidence
    ) {}
}
