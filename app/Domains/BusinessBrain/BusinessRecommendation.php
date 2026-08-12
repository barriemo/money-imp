<?php

namespace App\Domains\BusinessBrain;

class BusinessRecommendation
{
    public function __construct(
        public string $title,
        public string $reason,
        public int $priority,
        public int $confidence,
        public array $evidence = [],
        public array $data = []
    ) {}
}
