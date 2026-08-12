<?php

namespace App\Domains\ResourceIntelligence\Attribution;

class ResourceWorkAttribution
{
    public function __construct(
        public string $resource,

        public string $workLogId,

        public float $hours,

        public float $cost,

        public float $valueCreated,
    ) {}

    public function margin(): float
    {
        return $this->valueCreated - $this->cost;
    }

    public function marginPercentage(): float
    {
        if ($this->valueCreated === 0.0) {
            return 0.0;
        }

        return round(
            ($this->margin() / $this->valueCreated) * 100,
            2
        );
    }
}
