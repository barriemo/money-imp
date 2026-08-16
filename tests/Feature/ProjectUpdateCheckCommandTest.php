<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectUpdateCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_missing_update_requests(): void
    {
        $project =
            Project::create([
                'name' => 'Visit Dundee Platform',
                'status' => 'active',
            ]);

        ProjectUpdate::create([
            'project_id' => $project->id,
            'submitted_by' => 'Richard',
            'summary' => 'Old update',
            'created_at' => now()->subDays(20),
        ]);

        $this->artisan(
            'project:check-updates'
        )
            ->expectsOutputToContain(
                'New update requests created: 1'
            )
            ->expectsOutputToContain(
                'Visit Dundee Platform'
            )
            ->assertSuccessful();
    }
}
