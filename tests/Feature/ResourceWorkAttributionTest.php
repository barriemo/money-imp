<?php

namespace Tests\Feature;

use App\Domains\ResourceIntelligence\Attribution\ResourceWorkAttributionService;
use Tests\TestCase;

class ResourceWorkAttributionTest extends TestCase
{
    public function test_resource_work_contribution_can_calculate_margin(): void
    {
        $attribution =
            app(
                ResourceWorkAttributionService::class
            )->attribute(
                resource: 'John Smith',

                workLogId: 'work-1',

                hours: 20,

                costRate: 65,

                valueCreated: 3800
            );

        $this->assertSame(
            1300.0,
            $attribution->cost
        );

        $this->assertSame(
            2500.0,
            $attribution->margin()
        );

        $this->assertSame(
            65.79,
            $attribution->marginPercentage()
        );
    }
}
