<?php

namespace Tests\Feature;

use App\Domains\ResourceIntelligence\Allocation\AllocationVariance;
use App\Domains\ResourceIntelligence\Allocation\AllocationVarianceRepository;
use App\Domains\ResourceIntelligence\Allocation\Graph\AllocationVarianceGraphProvider;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummariser;
use Tests\TestCase;

class AllocationVarianceGraphProviderTest extends TestCase
{
    public function test_allocation_variance_enters_client_graph(): void
    {
        $repository =
            new AllocationVarianceRepository;

        $repository->add(
            new AllocationVariance(
                resource: 'John Smith',

                project: 'Walker CRM',

                allocatedHours: 40,

                actualHours: 65,

                costVariance: 1625
            )
        );

        $provider =
            new AllocationVarianceGraphProvider(
                $repository,

                app(
                    AllocationVarianceSummariser::class
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
