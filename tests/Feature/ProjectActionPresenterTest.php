<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Presenters\ProjectActionPresenter;
use App\Models\ProjectAction;
use App\Models\ProjectActionEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_presenter_returns_action_details(): void
    {
        $action = ProjectAction::factory()->create([
            'action' => 'Fix missing content',
            'priority' => 'high',
            'status' => ProjectAction::STATUS_OPEN,
            'assigned_to' => 'delivery_imp',
            'reason' => 'Launch readiness risk',
        ]);

        $result = app(ProjectActionPresenter::class)
            ->present($action);

        $this->assertSame(
            'Fix missing content',
            $result['action']
        );

        $this->assertSame(
            'high',
            $result['priority']
        );
    }

    public function test_presenter_includes_evidence(): void
    {
        $action = ProjectAction::factory()->create();

        ProjectActionEvidence::factory()->create([
            'project_action_id' => $action->id,
            'type' => 'metric',
            'description' => 'Health score dropped below threshold',
            'confidence' => 87,
        ]);

        $result = app(ProjectActionPresenter::class)
            ->present($action->fresh());

        $this->assertCount(
            1,
            $result['evidence']
        );

        $this->assertSame(
            87,
            $result['evidence'][0]['confidence']
        );
    }

    public function test_presenter_includes_lifecycle_state(): void
    {
        $action = ProjectAction::factory()->create([
            'status' => ProjectAction::STATUS_COMPLETED,
        ]);

        $result = app(ProjectActionPresenter::class)
            ->present($action);

        $this->assertSame(
            ProjectAction::STATUS_COMPLETED,
            $result['status']
        );
    }
}
