<?php

namespace Tests\Feature;

use App\Domains\ResourceIntelligence\Allocation\AllocationVariance;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummariser;
use Tests\TestCase;

class AllocationVarianceSummariserTest extends TestCase
{
    public function test_summarises_resource_allocation_risk(): void
    {
        $summary =
            app(
                AllocationVarianceSummariser::class
            )
                ->summarise(
                    collect([
                        new AllocationVariance(
                            resource: 'John Smith',

                            project: 'Walker CRM',

                            allocatedHours: 40,

                            actualHours: 65,

                            costVariance: 1625
                        ),

                        new AllocationVariance(
                            resource: 'Sarah Jones',

                            project: 'Website',

                            allocatedHours: 30,

                            actualHours: 42,

                            costVariance: 780
                        ),
                    ])
                );

        $this->assertSame(
            37,
            $summary->totalOverrunHours
        );

        $this->assertSame(
            2405.0,
            $summary->totalCostExposure
        );

        $this->assertSame(
            'John Smith',
            $summary->highestRiskResource
        );

        $this->assertTrue(
            $summary->attentionRequired
        );
    }
}
