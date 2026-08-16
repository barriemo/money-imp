<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectActionRecommendationEngine;
use App\Models\ProjectAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionRecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_priority_action_generates_recommendation(): void
    {
        $action = ProjectAction::factory()->create([
            'priority' => 'high',
            'action' => 'Improve customer onboarding',
        ]);

        $recommendation = app(
            ProjectActionRecommendationEngine::class
        )->generate($action);

        $this->assertSame(
            'next_step',
            $recommendation->type
        );

        $this->assertNotEmpty(
            $recommendation->recommendation
        );

        $this->assertGreaterThan(
            0,
            $recommendation->confidence
        );
    }
}
