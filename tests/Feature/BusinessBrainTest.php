<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessBrainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessBrainTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_brain_can_be_built_from_truth(): void
    {
        $brain = app(
            BusinessBrainService::class
        )->build();

        $this->assertCount(
            3,
            $brain->observations
        );

        $cash =
            $brain->observations
                ->firstWhere(
                    'type',
                    'cash_position'
                );

        $this->assertNotNull(
            $cash
        );

        $this->assertSame(
            0,
            $cash->confidence
        );
    }
}
