<?php

namespace Tests\Feature;

use App\Models\ProjectAction;
use App\Models\ProjectActionLearning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_can_have_learnings(): void
    {
        $action = ProjectAction::factory()->create();

        $learning = ProjectActionLearning::factory()->create([
            'project_action_id' => $action->id,
        ]);

        $this->assertCount(
            1,
            $action->learnings
        );

        $this->assertSame(
            $action->id,
            $learning->action->id
        );
    }

    public function test_learning_stores_impact(): void
    {
        $learning = ProjectActionLearning::factory()->create([
            'impact' => 'Reduced onboarding drop off',
        ]);

        $this->assertSame(
            'Reduced onboarding drop off',
            $learning->impact
        );
    }

    public function test_learning_stores_confidence(): void
    {
        $learning = ProjectActionLearning::factory()->create([
            'confidence' => 95,
        ]);

        $this->assertSame(
            95,
            $learning->confidence
        );
    }
}
