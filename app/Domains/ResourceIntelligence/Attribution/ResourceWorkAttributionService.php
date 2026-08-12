<?php

namespace App\Domains\ResourceIntelligence\Attribution;

class ResourceWorkAttributionService
{
    public function attribute(
        string $resource,
        string $workLogId,
        float $hours,
        float $costRate,
        float $valueCreated
    ): ResourceWorkAttribution {
        return new ResourceWorkAttribution(
            resource: $resource,

            workLogId: $workLogId,

            hours: $hours,

            cost: $hours * $costRate,

            valueCreated: $valueCreated
        );
    }
}
