<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Presenters\ProjectBrainPresenter;
use App\Models\Project;
use App\Models\ProjectAction;
use App\Models\ProjectActionEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectBrainPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_brain_groups_actions_by_priority(): void
    {
        $project = Project::factory()->create([
            'commercial_value' => 150000,
        ]);

        $urgent = ProjectAction::factory()->create([
            'project_id' => $project->id,
            'priority' => 'high',
        ]);

        ProjectActionEvidence::factory()->create([
            'project_action_id' => $urgent->id,
            'confidence' => 90,
        ]);

        $normal = ProjectAction::factory()->create([
            'project_id' => $project->id,
            'priority' => 'low',
        ]);

        $brain = app(
            ProjectBrainPresenter::class
        )->present(
            collect([
                $urgent,
                $normal,
            ])
        );

        $this->assertCount(
            1,
            $brain['urgent']
        );

        $this->assertCount(
            1,
            $brain['normal']
        );
    }
}
