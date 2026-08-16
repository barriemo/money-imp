<?php

namespace Tests\Feature;

use App\Models\ProjectAction;
use App\Models\ProjectActionCommitment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionCommitmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_can_have_commitments(): void
    {
        $action = ProjectAction::factory()->create();

        $commitment = ProjectActionCommitment::factory()->create([
            'project_action_id' => $action->id,
        ]);

        $this->assertCount(
            1,
            $action->commitments
        );

        $this->assertSame(
            $action->id,
            $commitment->action->id
        );
    }

    public function test_commitment_tracks_owner(): void
    {
        $commitment = ProjectActionCommitment::factory()->create([
            'owner' => 'Barrie',
        ]);

        $this->assertSame(
            'Barrie',
            $commitment->owner
        );
    }

    public function test_commitment_tracks_completion(): void
    {
        $commitment = ProjectActionCommitment::factory()->create([
            'status' => 'complete',
            'completed_at' => now(),
        ]);

        $this->assertSame(
            'complete',
            $commitment->status
        );

        $this->assertNotNull(
            $commitment->completed_at
        );
    }
}
