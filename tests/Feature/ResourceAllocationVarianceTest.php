<?php

namespace Tests\Feature;

use App\Domains\ResourceIntelligence\Allocation\AllocationVarianceService;
use Tests\TestCase;

class ResourceAllocationVarianceTest extends TestCase
{
    public function test_allocation_variance_identifies_overrun(): void
    {
        $variance =
            app(
                AllocationVarianceService::class
            )
                ->analyse(
                    [
                        'resource' => 'John Smith',

                        'project' => 'Walker CRM',

                        'hours' => 40,

                        'hourly_rate' => 65,
                    ],

                    65
                );

        $this->assertSame(
            25,
            $variance->hoursVariance()
        );

        $this->assertSame(
            1625.0,
            $variance->costVariance
        );

        $this->assertTrue(
            $variance->requiresAttention()
        );
    }
}
