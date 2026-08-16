<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Health\ProjectCommitmentRiskDetector;
use App\Models\Project;
use App\Models\ProjectCommunication;
use App\Models\ProjectDeliverable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCommitmentRiskDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_commitment_and_overdue_delivery_creates_risk(): void
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

            'summary' => 'Client requested launch before event.',

            'commitment' => 'Team to deliver launch before event.',

            'requested_by' => 'Mary',

            'occurred_at' => now(),
        ]);

        ProjectDeliverable::create([
            'project_id' => $project->id,

            'name' => 'Website launch',

            'owner' => 'Richard',

            'status' => 'not_started',

            'due_date' => now()->subDay(),
        ]);

        $risk =
            app(ProjectCommitmentRiskDetector::class)
                ->hasClientCommitmentRisk(
                    $project
                );

        $this->assertTrue(
            $risk
        );
    }
}
