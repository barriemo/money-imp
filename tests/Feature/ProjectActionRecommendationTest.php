<?php

namespace Tests\Feature;

use App\Models\ProjectAction;
use App\Models\ProjectActionRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_can_have_recommendations(): void
    {
        $action = ProjectAction::factory()->create();

        ProjectActionRecommendation::factory()->create([
            'project_action_id' => $action->id,
        ]);

        $this->assertCount(
            1,
            $action->recommendations
        );
    }

    public function test_recommendation_belongs_to_action(): void
    {
        $recommendation = ProjectActionRecommendation::factory()->create();

        $this->assertInstanceOf(
            ProjectAction::class,
            $recommendation->action
        );
    }

    public function test_recommendation_stores_confidence(): void
    {
        $recommendation = ProjectActionRecommendation::factory()->create([
            'confidence' => 85,
        ]);

        $this->assertSame(
            85,
            $recommendation->confidence
        );
    }

    public function test_action_can_have_multiple_recommendations(): void
    {
        $action = ProjectAction::factory()
            ->create();

        ProjectActionRecommendation::factory()
            ->count(3)
            ->create([
                'project_action_id' => $action->id,
            ]);

        $this->assertCount(
            3,
            $action->recommendations
        );
    }
}
