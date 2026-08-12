<?php

namespace App\Domains\ResourceIntelligence\Allocation;

class AllocationVariance
{
    public function __construct(
        public string $resource,

        public string $project,

        public int $allocatedHours,

        public int $actualHours,

        public float $costVariance,
    ) {}

    public function hoursVariance(): int
    {
        return $this->actualHours - $this->allocatedHours;
    }

    public function requiresAttention(): bool
    {
        return $this->hoursVariance() > 0;
    }
}
