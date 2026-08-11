<?php

namespace App\Domains\CheerfulCharlie\DTOs;

use App\Models\BusinessMemoryInsight;

readonly class CharliePriority
{
    public function __construct(
        public BusinessMemoryInsight $insight,
        public float $score,
        public float $financialImpactScore,
        public float $confidenceScore,
        public float $urgencyScore,
        public float $riskScore,
        public float $easeScore,
        public array $reasons,
    ) {}
}
