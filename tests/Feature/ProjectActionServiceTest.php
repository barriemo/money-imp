<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectActionService;
use App\Models\Project;
use App\Models\ProjectAction;
use App\Models\ProjectRisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_recommendation_creates_action(): void
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

        $created =
            app(ProjectActionService::class)
                ->createFromRecommendations($project);

        $this->assertCount(
            1,
            $created
        );

        $this->assertDatabaseHas(
            'project_actions',
            [
                'action' => 'Escalate unresolved project risk.',

                'priority' => 'high',
            ]
        );
    }

    public function test_duplicate_open_action_is_not_created(): void
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

        app(ProjectActionService::class)
            ->createFromRecommendations($project);

        $created =
            app(ProjectActionService::class)
                ->createFromRecommendations($project);

        $this->assertCount(
            0,
            $created
        );
    }

    public function test_completed_action_does_not_block_new_action(): void
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
            'reason' => 'Old issue',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $created =
            app(ProjectActionService::class)
                ->createFromRecommendations($project);

        $this->assertCount(
            1,
            $created
        );
    }
}
