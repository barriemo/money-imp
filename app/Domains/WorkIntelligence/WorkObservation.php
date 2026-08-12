<?php

namespace App\Domains\WorkIntelligence;

class WorkObservation
{
    public function __construct(
        public string $type,
        public mixed $value,
        public int $confidence,
        public array $metadata = [],
    ) {}
}
