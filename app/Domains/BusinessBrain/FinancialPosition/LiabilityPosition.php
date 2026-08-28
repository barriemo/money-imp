<?php

namespace App\Domains\BusinessBrain\FinancialPosition;

class LiabilityPosition
{
    public function __construct(
        public float $known,
        public float $vat,
        public float $paye,
        public float $other,
        public int $confidence,
        public bool $coverageComplete,
        public float $employerNic = 0,
        public float $payroll = 0
    ) {}
}
