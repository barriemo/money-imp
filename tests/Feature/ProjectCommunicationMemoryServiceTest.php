<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectCommunicationMemoryService;
use App\Models\Project;
use App\Models\ProjectCommunication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCommunicationMemoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_communication_preserves_client_commitment(): void
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

        $memory =
            app(ProjectCommunicationMemoryService::class)
                ->latest($project);

        $this->assertSame(
            'Team to deliver launch before event.',
            $memory[0]->commitment
        );

        $this->assertSame(
            'client',
            $memory[0]->direction
        );
    }
}
