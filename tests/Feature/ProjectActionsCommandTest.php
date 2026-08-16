<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAction;
use App\Models\ProjectActionEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_actions_command_presents_open_actions(): void
    {
        $project = Project::create([
            'name' => 'Walker CRM',
            'status' => 'active',
        ]);

        $action = ProjectAction::create([
            'project_id' => $project->id,
            'action' => 'Escalate unresolved project risk.',
            'priority' => 'high',
            'reason' => 'High priority project risk exists.',
            'status' => 'open',
        ]);

        ProjectActionEvidence::create([
            'project_action_id' => $action->id,
            'type' => 'risk',
            'description' => 'Customer delivery risk detected.',
            'confidence' => 91,
        ]);

        $this->artisan(
            'project:actions'
        )
            ->expectsOutputToContain(
                'Project Imp Action Queue'
            )
            ->expectsOutputToContain(
                'Walker CRM'
            )
            ->expectsOutputToContain(
                'HIGH'
            )
            ->expectsOutputToContain(
                'Escalate unresolved project risk.'
            )
            ->expectsOutputToContain(
                'Evidence: Customer delivery risk detected.'
            )
            ->assertSuccessful();
    }
}
