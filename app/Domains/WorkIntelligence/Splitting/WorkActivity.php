<?php

namespace App\Domains\WorkIntelligence\Splitting;

class WorkActivity
{
    public function __construct(
        public string $description,
        public int $minutes,
        public int $confidence,
        public array $metadata = [],
    ) {}
}
