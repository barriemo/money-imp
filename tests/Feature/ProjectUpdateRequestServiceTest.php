<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectUpdateRequestService;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\ProjectUpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectUpdateRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_project_creates_update_request(): void
    {
        $project =
            Project::create([
                'name' => 'Website rebuild',
                'status' => 'active',
            ]);

        ProjectUpdate::create([
            'project_id' => $project->id,
            'submitted_by' => 'Richard',
            'summary' => 'Initial progress update',
            'created_at' => now()->subDays(20),
        ]);

        $requests =
            app(ProjectUpdateRequestService::class)
                ->generate();

        $this->assertCount(
            1,
            $requests
        );

        $this->assertDatabaseHas(
            'project_update_requests',
            [
                'project_id' => $project->id,
                'status' => 'open',
                'reason' => 'No recent project progress update received.',
            ]
        );
    }

    public function test_recent_project_update_does_not_create_request(): void
    {
        $project =
            Project::create([
                'name' => 'Website rebuild',
                'status' => 'active',
            ]);

        ProjectUpdate::create([
            'project_id' => $project->id,
            'submitted_by' => 'Richard',
            'summary' => 'Progress made',
            'created_at' => now()->subDays(2),
        ]);

        $requests =
            app(ProjectUpdateRequestService::class)
                ->generate();

        $this->assertCount(
            0,
            $requests
        );
    }

    public function test_existing_open_request_is_not_duplicated(): void
    {
        $project =
            Project::create([
                'name' => 'Website rebuild',
                'status' => 'active',
            ]);

        ProjectUpdateRequest::create([
            'project_id' => $project->id,
            'reason' => 'Awaiting update',
            'status' => 'open',
        ]);

        $requests =
            app(ProjectUpdateRequestService::class)
                ->generate();

        $this->assertCount(
            0,
            $requests
        );
    }
}
