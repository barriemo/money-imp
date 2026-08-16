<?php

namespace App\Domains\BusinessBrain\CashTruth;

class CanonicalCashMovement
{
    public function __construct(
        public string $id,

        public string $date,

        public float $amount,

        public ?string $clientId,

        public string $description,

        public bool $allocated,

        public int $evidenceCount,

        public int $confidence
    ) {}
}
