<?php

namespace Tests\Feature;

use App\Domains\ResourceIntelligence\Allocation\AllocationVariance;
use App\Domains\ResourceIntelligence\Allocation\AllocationVarianceRepository;
use App\Domains\ResourceIntelligence\Allocation\Providers\ClientAllocationVarianceProvider;
use Tests\TestCase;

class ClientAllocationVarianceProviderTest extends TestCase
{
    public function test_client_allocation_provider_returns_summary(): void
    {
        $repository =
            app(
                AllocationVarianceRepository::class
            );

        $repository->add(
            new AllocationVariance(
                resource: 'Developer',

                project: 'Website',

                allocatedHours: 10,

                actualHours: 20,

                costVariance: 1000
            )
        );

        $summary =
            app(
                ClientAllocationVarianceProvider::class
            )->provide(
                'client-1'
            );

        $this->assertSame(
            1000.0,
            $summary->totalCostExposure
        );

        $this->assertTrue(
            $summary->attentionRequired
        );
    }
}
