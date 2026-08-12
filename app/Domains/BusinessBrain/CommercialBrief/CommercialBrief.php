<?php

namespace App\Domains\BusinessBrain\CommercialBrief;

class CommercialBrief
{
    public function __construct(
        public string $health,
        public float $recoveryValue,
        public int $recoveryCount,
        public ?float $largestOpportunity,
        public int $confidence,
        public array $recommendations = [],
    ) {}
}
