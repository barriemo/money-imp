<?php

namespace App\Domains\BusinessBrain\BankTruth;

class CanonicalPaymentEvidence
{
    public function __construct(
        public string $id,

        public string $date,

        public float $amount,

        public ?string $clientId,

        public string $description,

        public int $confidence,

        public int $evidenceCount,

        public array $evidenceIds
    ) {}
}
