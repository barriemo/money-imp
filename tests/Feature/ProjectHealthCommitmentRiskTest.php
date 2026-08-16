<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectHealthService;
use App\Models\Project;
use App\Models\ProjectCommunication;
use App\Models\ProjectDeliverable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectHealthCommitmentRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_commitment_breach_blocks_project(): void
    {
        $project =
            Project::create([
                'name' => 'Website rebuild',
                'status' => 'active',
            ]);

        ProjectCommunication::create([
            'project_id' => $project->id,
            'type' => 'meeting',
            'direction' => 'client',
            'summary' => 'Client needs launch before event.',
            'commitment' => 'Launch before event.',
            'requested_by' => 'Mary',
            'occurred_at' => now(),
        ]);

        ProjectDeliverable::create([
            'project_id' => $project->id,
            'name' => 'Launch',
            'status' => 'not_started',
            'due_date' => now()->subDay(),
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
