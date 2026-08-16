<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectBriefService;
use App\Models\Project;
use App\Models\ProjectRisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectBriefServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_brief_surfaces_blocked_projects(): void
    {
        $project =
            Project::create([
                'name' => 'Walker CRM',
            ]);

        ProjectRisk::create([
            'project_id' => $project->id,
            'description' => 'Client approval missing',
            'severity' => 'high',
            'status' => 'open',
        ]);

        $brief =
            app(ProjectBriefService::class)
                ->current();

        $this->assertSame(
            1,
            $brief->blockedProjects
        );
    }

    public function test_project_brief_exposes_health_reason(): void
    {
        $project =
            Project::create([
                'name' => 'Walker CRM',
            ]);

        ProjectRisk::create([
            'project_id' => $project->id,
            'description' => 'Client approval missing',
            'severity' => 'high',
            'status' => 'open',
        ]);

        $brief =
            app(ProjectBriefService::class)
                ->current();

        $priority =
            $brief->priorityProjects[0];

        $this->assertSame(
            'blocked',
            $priority['health']
        );

        $this->assertContains(
            'High priority project risk exists.',
            $priority['reasons']
        );
    }
}
