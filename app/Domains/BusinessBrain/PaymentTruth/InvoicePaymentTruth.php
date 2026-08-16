<?php

namespace App\Domains\BusinessBrain\PaymentTruth;

class InvoicePaymentTruth
{
    public function __construct(
        public string $invoiceId,

        public ?string $invoiceNumber,

        public ?string $clientId,

        public ?string $client,

        public float $grossAmount,

        public float $bankConfirmedPaid,

        public float $suggestedPaid,

        public float $provenOutstanding,

        public float $accountingPaid,

        public float $accountingOutstanding,

        public string $status,

        public bool $accountingConflict,

        public int $approvedPaymentCount,

        public int $suggestedPaymentCount,

        public array $bankTransactionIds,

        public int $confidence
    ) {}
}
