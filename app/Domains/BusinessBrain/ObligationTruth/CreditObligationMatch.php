<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

class CreditObligationMatch
{
    public function __construct(
        public string $bankTransactionId,

        public float $amount,

        public string $transactionDate,

        public string $description,

        public int $confidence
    ) {}
}
