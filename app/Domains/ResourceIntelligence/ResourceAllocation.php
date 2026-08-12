<?php

namespace App\Domains\ResourceIntelligence;

class ResourceAllocation
{
    public function __construct(
        public string $resource,

        public string $project,

        public int $expectedHours,
    ) {}
}
