<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectHealthService;
use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectHealthUpdateRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_project_update_places_project_at_risk(): void
    {
        $project =
            Project::create([
                'name' => 'Website rebuild',
                'status' => 'active',
            ]);

        ProjectUpdate::create([
            'project_id' => $project->id,

            'submitted_by' => 'Richard',

            'summary' => 'Building homepage',

            'created_at' => now()->subDays(20),
        ]);

        $health =
            app(ProjectHealthService::class)
                ->assess(
                    $project
                );

        $this->assertSame(
            'at_risk',
            $health->status
        );
    }
}
