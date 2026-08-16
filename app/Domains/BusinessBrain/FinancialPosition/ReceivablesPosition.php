<?php

namespace App\Domains\BusinessBrain\FinancialPosition;

class ReceivablesPosition
{
    public function __construct(
        public float $ledgerOutstanding,

        public float $paymentsWaitingAllocation,

        public ?float $verifiedCollectible,

        public int $confidence
    ) {}
}
