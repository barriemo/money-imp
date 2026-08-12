<?php

namespace App\Domains\ResourceIntelligence\Allocation;

class AllocationVarianceService
{
    public function analyse(
        array $allocation,
        int $actualHours
    ): AllocationVariance {
        $costVariance =
            ($actualHours - $allocation['hours'])
            *
            $allocation['hourly_rate'];

        return new AllocationVariance(
            resource: $allocation['resource'],

            project: $allocation['project'],

            allocatedHours: $allocation['hours'],

            actualHours: $actualHours,

            costVariance: $costVariance
        );
    }
}
