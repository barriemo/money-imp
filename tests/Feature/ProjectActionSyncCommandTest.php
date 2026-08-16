<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAction;
use App\Models\ProjectRisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_actions_from_project_recommendations(): void
    {
        $project =
            Project::create([
                'name' => 'Walker CRM',
                'status' => 'active',
            ]);

        ProjectRisk::create([
            'project_id' => $project->id,
            'description' => 'Client approval missing',
            'severity' => 'high',
            'status' => 'open',
        ]);

        $this->artisan(
            'project:sync-actions'
        )
            ->expectsOutputToContain(
                'Actions created: 1'
            )
            ->assertSuccessful();

        $this->assertDatabaseHas(
            'project_actions',
            [
                'project_id' => $project->id,
                'status' => 'open',
            ]
        );
    }

    public function test_sync_does_not_duplicate_existing_actions(): void
    {
        $project =
            Project::create([
                'name' => 'Walker CRM',
                'status' => 'active',
            ]);

        ProjectRisk::create([
            'project_id' => $project->id,
            'description' => 'Client approval missing',
            'severity' => 'high',
            'status' => 'open',
        ]);

        ProjectAction::create([
            'project_id' => $project->id,
            'action' => 'Escalate unresolved project risk.',
            'priority' => 'high',
            'reason' => 'High priority project risk exists.',
            'status' => 'open',
        ]);

        $this->artisan(
            'project:sync-actions'
        )
            ->expectsOutputToContain(
                'Actions created: 0'
            )
            ->assertSuccessful();
    }
}
