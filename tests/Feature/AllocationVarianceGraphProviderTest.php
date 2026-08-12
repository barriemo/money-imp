<?php

namespace Tests\Feature;

use App\Domains\ResourceIntelligence\Allocation\Graph\AllocationVarianceGraphProvider;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;
use Tests\TestCase;

class AllocationVarianceGraphProviderTest extends TestCase
{
    public function test_allocation_variance_enters_client_graph(): void
    {
        $provider =
            new AllocationVarianceGraphProvider(
                new AllocationVarianceSummary(
                    totalOverrunHours: 25,

                    totalCostExposure: 1625,

                    highestRiskResource: 'John Smith',

                    attentionRequired: true
                )
            );

        $result =
            $provider->build(
                'client-1'
            );

        $this->assertCount(
            1,
            $result->nodes
        );

        $this->assertSame(
            1625.0,
            $result->nodes
                ->first()
                ->attributes['cost_exposure']
        );

        $this->assertCount(
            1,
            $result->edges
        );
    }
}
