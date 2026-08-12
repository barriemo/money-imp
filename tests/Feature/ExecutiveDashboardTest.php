<?php

namespace Tests\Feature;

use App\Domains\Executive\ExecutiveAnswer;
use App\Domains\Executive\ExecutiveDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_contains_viability_answer(): void
    {
        $dashboard = app(
            ExecutiveDashboard::class
        )->build();

        $this->assertArrayHasKey(
            'viability',
            $dashboard
        );

        $this->assertInstanceOf(
            ExecutiveAnswer::class,
            $dashboard[
                'viability'
            ]
        );
    }
}
