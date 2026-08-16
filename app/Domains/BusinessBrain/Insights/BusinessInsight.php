<?php

namespace App\Domains\BusinessBrain\Insights;

class BusinessInsight
{
    public function __construct(
        public string $headline,

        public string $status,

        public string $summary,

        public array $metrics,

        public array $risks,

        public array $actions,

        public int $confidence
    ) {}
}
