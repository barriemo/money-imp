<?php

namespace App\Domains\BusinessBrain\PaymentTruth;

class InvoiceBankMatch
{
    public function __construct(
        public string $bankTransactionId,

        public float $amount,

        public string $status,

        public ?float $confidence,

        public ?string $matchMethod
    ) {}
}
