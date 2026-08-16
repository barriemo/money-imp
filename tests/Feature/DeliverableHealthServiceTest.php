<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\DeliverableHealthService;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliverableHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_deliverable_is_flagged(): void
    {
        $project =
            Project::create([
                'name' => 'Website rebuild',
                'status' => 'active',
            ]);

        $deliverable =
            ProjectDeliverable::create([
                'project_id' => $project->id,
                'name' => 'Homepage',
                'due_date' => now()->subDay(),
                'status' => 'not_started',
            ]);

        $health =
            app(DeliverableHealthService::class)
                ->assess(
                    $deliverable
                );

        $this->assertSame(
            'overdue',
            $health->status
        );
    }
}
