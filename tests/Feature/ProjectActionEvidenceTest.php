<?php

namespace Tests\Feature;

use App\Models\ProjectAction;
use App\Models\ProjectActionEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_can_have_evidence(): void
    {
        $action = ProjectAction::factory()->create();

        $evidence = ProjectActionEvidence::factory()->create([
            'project_action_id' => $action->id,
        ]);

        $this->assertTrue(
            $action->evidence
                ->contains($evidence)
        );
    }

    public function test_evidence_belongs_to_action(): void
    {
        $action = ProjectAction::factory()->create();

        $evidence = ProjectActionEvidence::factory()->create([
            'project_action_id' => $action->id,
        ]);

        $this->assertEquals(
            $action->id,
            $evidence->projectAction->id
        );
    }

    public function test_evidence_stores_confidence(): void
    {
        $evidence = ProjectActionEvidence::factory()->create([
            'confidence' => 87,
        ]);

        $this->assertEquals(
            87,
            $evidence->confidence
        );
    }

    public function test_action_can_have_multiple_evidence_items(): void
    {
        $action = ProjectAction::factory()->create();

        ProjectActionEvidence::factory()
            ->count(3)
            ->create([
                'project_action_id' => $action->id,
            ]);

        $this->assertCount(
            3,
            $action->evidence
        );
    }
}