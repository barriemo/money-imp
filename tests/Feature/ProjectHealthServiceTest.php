<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectHealthService;
use App\Models\Project;
use App\Models\ProjectRisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_priority_project_risk_blocks_project(): void
    {
        $project =
            Project::create([
                'name' => 'Website rebuild',
            ]);

        ProjectRisk::create([
            'project_id' => $project->id,
            'description' => 'Client approval missing',
            'severity' => 'high',
            'status' => 'open',
        ]);

        $health =
            app(ProjectHealthService::class)
                ->assess(
                    $project
                );

        $this->assertSame(
            'blocked',
            $health->status
        );
    }
}
