<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

class StatutoryPaymentEvidence
{
    public function __construct(
        public readonly string $bankTransactionId,
        public readonly string $date,
        public readonly float $amount,
        public readonly string $authority,
        public readonly ?string $taxType,
        public readonly string $classification,
        public readonly int $confidence,
        public readonly string $description,
        public readonly array $signals = [],
    ) {}
}
