<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAction;
use App\Models\ProjectActionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectHistoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_history_command_presents_action_history(): void
    {
        $project =
            Project::create([
                'name' => 'Walker CRM',
                'status' => 'active',
            ]);

        $action =
            ProjectAction::create([
                'project_id' => $project->id,
                'action' => 'Resolve delivery risk.',
                'priority' => 'high',
                'reason' => 'Risk detected.',
                'status' => ProjectAction::STATUS_COMPLETED,
            ]);

        ProjectActionEvent::create([
            'project_action_id' => $action->id,
            'type' => 'completed',
            'payload' => [],
        ]);

        $this->artisan(
            'project:history'
        )
            ->expectsOutputToContain(
                'Project Action History'
            )
            ->expectsOutputToContain(
                'Walker CRM'
            )
            ->expectsOutputToContain(
                'Resolve delivery risk.'
            )
            ->expectsOutputToContain(
                'Timeline: completed'
            )
            ->assertSuccessful();
    }
}
