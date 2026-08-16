<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Health\ProjectUpdateHealthDetector;
use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectUpdateHealthDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_without_recent_update_needs_attention(): void
    {
        $project =
            Project::create([
                'name' => 'Website rebuild',
                'status' => 'active',
            ]);

        ProjectUpdate::create([
            'project_id' => $project->id,

            'submitted_by' => 'Richard',

            'summary' => 'Initial update',

            'progress' => 'Building homepage',

            'created_at' => now()->subDays(20),
        ]);

        $needsUpdate =
            app(ProjectUpdateHealthDetector::class)
                ->needsUpdate(
                    $project
                );

        $this->assertTrue(
            $needsUpdate
        );
    }
}
