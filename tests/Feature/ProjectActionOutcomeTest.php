<?php

namespace Tests\Feature;

use App\Models\ProjectAction;
use App\Models\ProjectActionOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionOutcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_can_have_outcomes(): void
    {
        $action = ProjectAction::factory()->create();

        ProjectActionOutcome::factory()->create([
            'project_action_id' => $action->id,
        ]);

        $this->assertCount(
            1,
            $action->outcomes
        );
    }

    public function test_outcome_belongs_to_action(): void
    {
        $action = ProjectAction::factory()->create();

        $outcome = ProjectActionOutcome::factory()->create([
            'project_action_id' => $action->id,
        ]);

        $this->assertSame(
            $action->id,
            $outcome->projectAction->id
        );
    }

    public function test_outcome_stores_confidence(): void
    {
        $outcome = ProjectActionOutcome::factory()->create([
            'confidence' => 95,
        ]);

        $this->assertSame(
            95,
            $outcome->confidence
        );
    }

    public function test_action_can_have_multiple_outcomes(): void
    {
        $action = ProjectAction::factory()->create();

        ProjectActionOutcome::factory()
            ->count(3)
            ->create([
                'project_action_id' => $action->id,
            ]);

        $this->assertCount(
            3,
            $action->outcomes
        );
    }
}
