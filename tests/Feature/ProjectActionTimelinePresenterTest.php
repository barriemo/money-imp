<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Presenters\ProjectActionTimelinePresenter;
use App\Models\ProjectAction;
use App\Models\ProjectActionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionTimelinePresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_presenter_returns_action_timeline(): void
    {
        $action = ProjectAction::factory()->create([
            'action' => 'Resolve delivery risk.',
        ]);

        ProjectActionEvent::factory()->create([
            'project_action_id' => $action->id,
            'type' => 'approved',
        ]);

        ProjectActionEvent::factory()->create([
            'project_action_id' => $action->id,
            'type' => 'completed',
        ]);

        $timeline = app(
            ProjectActionTimelinePresenter::class
        )->present($action->load('events'));

        $this->assertSame(
            'Resolve delivery risk.',
            $timeline['action']
        );

        $this->assertCount(
            2,
            $timeline['timeline']
        );

        $this->assertSame(
            'approved',
            $timeline['timeline'][0]['type']
        );
    }

    public function test_presenter_includes_event_payload(): void
    {
        $action = ProjectAction::factory()->create();

        ProjectActionEvent::factory()->create([
            'project_action_id' => $action->id,
            'type' => 'assigned',
            'payload' => [
                'owner' => 'delivery_imp',
            ],
        ]);

        $timeline = app(
            ProjectActionTimelinePresenter::class
        )->present($action->load('events'));

        $this->assertSame(
            'delivery_imp',
            $timeline['timeline'][0]['payload']['owner']
        );
    }
}
