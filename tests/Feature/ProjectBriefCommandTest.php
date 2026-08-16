<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\ProjectRisk;
use App\Models\ProjectUpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectBriefCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_brief_command_presents_project_position(): void
    {
        $project =
            Project::create([
                'name' => 'Visit Dundee Platform',
                'status' => 'active',
            ]);

        ProjectDeliverable::create([
            'project_id' => $project->id,
            'name' => 'Homepage',
            'owner' => 'Richard',
            'due_date' => now()->subDay(),
            'status' => 'not_started',
        ]);

        $this->artisan(
            'project:brief'
        )
            ->expectsOutputToContain(
                'Project Imp'
            )
            ->expectsOutputToContain(
                'Active projects: 1'
            )
            ->expectsOutputToContain(
                'Delivery risks:'
            )
            ->expectsOutputToContain(
                'Homepage'
            )
            ->assertSuccessful();
    }

    public function test_project_brief_command_explains_priority_project_reason(): void
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
            'project:brief'
        )
            ->expectsOutputToContain(
                'Walker CRM'
            )
            ->expectsOutputToContain(
                'Status: BLOCKED'
            )
            ->expectsOutputToContain(
                'Reasons:'
            )
            ->expectsOutputToContain(
                'High priority project risk exists.'
            )
            ->assertSuccessful();
    }

    public function test_project_brief_command_shows_outstanding_update_requests(): void
    {
        $project =
            Project::create([
                'name' => 'Visit Dundee Platform',
                'status' => 'active',
            ]);

        ProjectUpdateRequest::create([
            'project_id' => $project->id,
            'requested_from' => 'Richard',
            'reason' => 'No recent project progress update received.',
            'status' => 'open',
        ]);

        $this->artisan(
            'project:brief'
        )
            ->expectsOutputToContain(
                'Outstanding update requests:'
            )
            ->expectsOutputToContain(
                'Visit Dundee Platform'
            )
            ->expectsOutputToContain(
                'Requested from: Richard'
            )
            ->assertSuccessful();
    }

    public function test_project_brief_command_shows_recommended_actions(): void
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
            'project:brief'
        )
            ->expectsOutputToContain(
                'Recommended actions:'
            )
            ->expectsOutputToContain(
                'Escalate unresolved project risk.'
            )
            ->assertSuccessful();
    }
}
