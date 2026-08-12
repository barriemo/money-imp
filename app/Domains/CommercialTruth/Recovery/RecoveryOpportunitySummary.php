<?php

namespace App\Domains\CommercialTruth\Recovery;

class RecoveryOpportunitySummary
{
    public function __construct(
        public string $clientId,
        public int $opportunityCount,
        public float $totalValue,
        public float $highestValue,
        public int $confidence,
    ) {}
}
