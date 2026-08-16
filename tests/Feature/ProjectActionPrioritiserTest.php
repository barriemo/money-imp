<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectActionPrioritiser;
use App\Models\Project;
use App\Models\ProjectAction;
use App\Models\ProjectActionEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionPrioritiserTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_value_high_confidence_action_is_urgent(): void
    {
        $project = Project::factory()->create([
            'commercial_value' => 150000,
        ]);

        $action = ProjectAction::factory()->create([
            'project_id' => $project->id,
            'priority' => 'high',
        ]);

        ProjectActionEvidence::factory()->create([
            'project_action_id' => $action->id,
            'confidence' => 90,
        ]);

        $result = app(
            ProjectActionPrioritiser::class
        )->prioritise(
            $action->load('evidence', 'project')
        );

        $this->assertSame(
            'urgent',
            $result['category']
        );

        $this->assertGreaterThanOrEqual(
            80,
            $result['score']
        );
    }

    public function test_low_signal_action_is_normal(): void
    {
        $action = ProjectAction::factory()->create([
            'priority' => 'low',
        ]);

        $result = app(
            ProjectActionPrioritiser::class
        )->prioritise(
            $action->load('evidence', 'project')
        );

        $this->assertSame(
            'normal',
            $result['category']
        );
    }
}
