<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Presenters\ProjectActionIntelligencePresenter;
use App\Models\Project;
use App\Models\ProjectAction;
use App\Models\ProjectActionEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionIntelligencePriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_presenter_includes_priority_assessment(): void
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

        $data = app(
            ProjectActionIntelligencePresenter::class
        )->present($action);

        $this->assertSame(
            'urgent',
            $data['priority']['category']
        );

        $this->assertGreaterThanOrEqual(
            80,
            $data['priority']['score']
        );
    }
}
