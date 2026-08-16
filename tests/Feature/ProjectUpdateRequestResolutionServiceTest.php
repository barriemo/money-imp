<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectUpdateRequestResolutionService;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\ProjectUpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectUpdateRequestResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_update_resolves_open_request(): void
    {
        $project =
            Project::create([
                'name' => 'Visit Dundee Platform',
                'status' => 'active',
            ]);

        $request =
            ProjectUpdateRequest::create([
                'project_id' => $project->id,
                'requested_from' => 'Richard',
                'reason' => 'Waiting for progress update',
                'status' => 'open',
                'created_at' => now()->subDay(),
            ]);

        ProjectUpdate::create([
            'project_id' => $project->id,
            'submitted_by' => 'Richard',
            'summary' => 'Homepage completed',
            'created_at' => now(),
        ]);

        $resolved =
            app(ProjectUpdateRequestResolutionService::class)
                ->resolveFromUpdates();

        $this->assertCount(
            1,
            $resolved
        );

        $this->assertDatabaseHas(
            'project_update_requests',
            [
                'status' => 'responded',
                'response' => 'Homepage completed',
            ]
        );
    }
}
