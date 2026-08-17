<?php

namespace App\Domains\BusinessBrain\Finance\DTOs;

class CashRiskAssessment
{
    public function __construct(
        public readonly float $cashPosition,
        public readonly float $outstandingInvoices,
        public readonly float $knownLiabilities,
        public readonly float $unknownExposure,
        public readonly int $confidence,
        public readonly array $evidence = [],
    ) {}

    public function riskScore(): int
    {
        $score = 0;

        if ($this->unknownExposure > 0) {
            $score += 40;
        }

        if ($this->outstandingInvoices > $this->cashPosition) {
            $score += 30;
        }

        if ($this->knownLiabilities > $this->cashPosition) {
            $score += 30;
        }

        return min(
            100,
            $score
        );
    }
}