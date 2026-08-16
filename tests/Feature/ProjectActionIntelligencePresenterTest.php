<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Presenters\ProjectActionIntelligencePresenter;
use App\Models\ProjectAction;
use App\Models\ProjectActionEvent;
use App\Models\ProjectActionOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionIntelligencePresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_presenter_returns_complete_action_intelligence(): void
    {
        $action = ProjectAction::factory()->create();

        ProjectActionOutcome::factory()->create([
            'project_action_id' => $action->id,
            'value' => '+14%',
            'confidence' => 90,
        ]);

        ProjectActionEvent::factory()->create([
            'project_action_id' => $action->id,
            'type' => 'completed',
        ]);

        $data = app(
            ProjectActionIntelligencePresenter::class
        )->present($action);

        $this->assertArrayHasKey(
            'action',
            $data
        );

        $this->assertArrayHasKey(
            'timeline',
            $data
        );

        $this->assertArrayHasKey(
            'assessment',
            $data
        );

        $this->assertSame(
            'success',
            $data['assessment'][0]['result']
        );
    }
}
