<?php

namespace App\Domains\WorkIntelligence\Analysis;

class BillabilityAssessment
{
    public function __construct(
        public bool $billable,
        public int $confidence,
        public string $reason,
        public array $signals = [],
    ) {}
}
