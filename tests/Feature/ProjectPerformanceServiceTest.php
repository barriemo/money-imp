<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectPerformanceService;
use App\Models\Project;
use App\Models\ProjectUpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_performance_tracks_response_speed(): void
    {
        $project =
            Project::create([
                'name' => 'Visit Dundee Platform',
                'status' => 'active',
            ]);

        ProjectUpdateRequest::create([
            'project_id' => $project->id,
            'reason' => 'Awaiting update',
            'status' => 'responded',
            'created_at' => now()->subDays(10),
            'responded_at' => now(),
        ]);

        $performance =
            app(ProjectPerformanceService::class)
                ->current();

        $this->assertSame(
            0,
            $performance->openUpdateRequests
        );

        $this->assertSame(
            1,
            $performance->resolvedUpdateRequests
        );

        $this->assertSame(
            10.0,
            $performance->averageResponseDays
        );
    }
}
