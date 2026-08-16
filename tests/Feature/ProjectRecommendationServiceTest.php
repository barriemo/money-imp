<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectRecommendationService;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\ProjectRisk;
use App\Models\ProjectUpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_project_recommends_escalation(): void
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

        $recommendations =
            app(ProjectRecommendationService::class)
                ->for($project);

        $this->assertSame(
            'Escalate unresolved project risk.',
            $recommendations->first()->action
        );
    }

    public function test_open_update_request_recommends_progress_request(): void
    {
        $project =
            Project::create([
                'name' => 'Visit Dundee Platform',
                'status' => 'active',
            ]);

        ProjectUpdateRequest::create([
            'project_id' => $project->id,
            'reason' => 'Waiting for update',
            'status' => 'open',
        ]);

        $recommendations =
            app(ProjectRecommendationService::class)
                ->for($project);

        $this->assertSame(
            'Request project progress update.',
            $recommendations->first()->action
        );
    }

    public function test_overdue_deliverable_recommends_delivery_review(): void
    {
        $project =
            Project::create([
                'name' => 'Website',
                'status' => 'active',
            ]);

        ProjectDeliverable::create([
            'project_id' => $project->id,
            'name' => 'Homepage',
            'due_date' => now()->subDay(),
            'status' => 'not_started',
        ]);

        $recommendations =
            app(ProjectRecommendationService::class)
                ->for($project);

        $this->assertSame(
            'Review overdue delivery ownership.',
            $recommendations->first()->action
        );
    }

    public function test_healthy_project_has_no_recommendations(): void
    {
        $project =
            Project::create([
                'name' => 'Healthy Project',
                'status' => 'active',
            ]);

        $recommendations =
            app(ProjectRecommendationService::class)
                ->for($project);

        $this->assertCount(
            0,
            $recommendations
        );
    }

    public function test_high_priority_recommendations_appear_before_medium(): void
    {
        $project =
            Project::create([
                'name' => 'Mixed Issues Project',
                'status' => 'active',
            ]);

        ProjectUpdateRequest::create([
            'project_id' => $project->id,
            'reason' => 'Waiting for update',
            'status' => 'open',
        ]);

        ProjectRisk::create([
            'project_id' => $project->id,
            'description' => 'Critical blocker',
            'severity' => 'high',
            'status' => 'open',
        ]);

        $recommendations =
            app(ProjectRecommendationService::class)
                ->for($project);

        $this->assertSame(
            'high',
            $recommendations->first()->priority
        );
    }
}
