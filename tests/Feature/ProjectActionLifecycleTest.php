<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_can_be_approved(): void
    {
        $action = ProjectAction::factory()->create([
            'status' => ProjectAction::STATUS_OPEN,
        ]);

        $action->approve();

        $this->assertEquals(
            ProjectAction::STATUS_APPROVED,
            $action->fresh()->status
        );
    }

    public function test_action_can_be_assigned(): void
    {
        $action = ProjectAction::factory()->create([
            'status' => ProjectAction::STATUS_OPEN,
        ]);

        $action->assignTo('delivery_imp');

        $action = $action->fresh();

        $this->assertEquals(
            ProjectAction::STATUS_ASSIGNED,
            $action->status
        );

        $this->assertEquals(
            'delivery_imp',
            $action->assigned_to
        );
    }

    public function test_completed_action_records_timestamp(): void
    {
        $action = ProjectAction::factory()->create([
            'status' => ProjectAction::STATUS_IN_PROGRESS,
        ]);

        $action->complete();

        $action = $action->fresh();

        $this->assertEquals(
            ProjectAction::STATUS_COMPLETED,
            $action->status
        );

        $this->assertNotNull($action->completed_at);
    }

    public function test_completed_action_can_be_verified(): void
    {
        $action = ProjectAction::factory()->create([
            'status' => ProjectAction::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $action->verify();

        $action = $action->fresh();

        $this->assertEquals(
            ProjectAction::STATUS_VERIFIED,
            $action->status
        );

        $this->assertNotNull($action->verified_at);
    }

    public function test_action_cannot_be_verified_before_completion(): void
    {
        $this->expectException(\DomainException::class);

        $action = ProjectAction::factory()->create([
            'status' => ProjectAction::STATUS_OPEN,
        ]);

        $action->verify();
    }
}