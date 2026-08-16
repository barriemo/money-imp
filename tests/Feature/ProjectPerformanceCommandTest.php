<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectUpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPerformanceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_performance_command_presents_delivery_metrics(): void
    {
        $project =
            Project::create([
                'name' => 'Walker CRM',
                'status' => 'active',
            ]);

        ProjectUpdateRequest::create([
            'project_id' => $project->id,
            'reason' => 'Awaiting update',
            'status' => 'responded',
            'created_at' => now()->subDays(10),
            'responded_at' => now(),
        ]);

        $this->artisan(
            'project:performance'
        )
            ->expectsOutputToContain(
                'Project Imp Performance'
            )
            ->expectsOutputToContain(
                'Resolved update requests: 1'
            )
            ->expectsOutputToContain(
                'Average response time: 10 days'
            )
            ->assertSuccessful();
    }
}
