<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectActionPrioritiser;
use App\Models\Project;
use App\Models\ProjectAction;
use App\Models\ProjectActionEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionPriorityDriversTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_includes_business_drivers(): void
    {
        $project = Project::factory()->create([
            'commercial_value' => 150000,
        ]);

        $action = ProjectAction::factory()->create([
            'project_id' => $project->id,
            'priority' => 'high',
        ]);

        ProjectActionEvidence::factory()->create([
            'project_action_id' => $action->id,
            'confidence' => 90,
        ]);

        $result = app(
            ProjectActionPrioritiser::class
        )->prioritise(
            $action->load('evidence', 'project')
        );

        $this->assertContains(
            'High priority action',
            $result['drivers']
        );

        $this->assertContains(
            'Strong evidence confidence',
            $result['drivers']
        );

        $this->assertContains(
            'High commercial value',
            $result['drivers']
        );
    }
}
