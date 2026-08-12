<?php

namespace App\Domains\ResourceIntelligence\Allocation\Summary;

class AllocationVarianceSummary
{
    public function __construct(
        public int $totalOverrunHours,

        public float $totalCostExposure,

        public ?string $highestRiskResource,

        public bool $attentionRequired,
    ) {}
}
