<?php

namespace App\Domains\BusinessBrain\CashTruth;

class CanonicalCashPosition
{
    public function __construct(
        public float $totalIncomingCash,

        public float $allocatedCustomerCash,

        public float $unallocatedCash,

        public int $movementCount,

        public int $confidence
    ) {}
}
