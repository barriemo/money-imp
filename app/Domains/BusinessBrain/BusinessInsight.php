<?php

namespace App\Domains\BusinessBrain;

class BusinessInsight
{
    public function __construct(
        public string $type,
        public string $summary,
        public int $confidence,
        public array $evidence = [],
        public array $data = []
    ) {}
}
