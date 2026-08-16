<?php

namespace Tests\Feature;

use App\Models\ProjectAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionLifecycleEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_creates_event(): void
    {
        $action = ProjectAction::factory()->create();

        $action->approve();

        $this->assertDatabaseHas('project_action_events', [
            'project_action_id' => $action->id,
            'type' => 'approved',
        ]);
    }

    public function test_assignment_creates_event_with_owner(): void
    {
        $action = ProjectAction::factory()->create();

        $action->assignTo('delivery_imp');

        $event = $action->events()->first();

        $this->assertSame(
            'assigned',
            $event->type
        );

        $this->assertSame(
            'delivery_imp',
            $event->payload['owner']
        );
    }

    public function test_completion_creates_event(): void
    {
        $action = ProjectAction::factory()->create();

        $action->complete();

        $this->assertDatabaseHas('project_action_events', [
            'project_action_id' => $action->id,
            'type' => 'completed',
        ]);
    }

    public function test_verification_creates_event(): void
    {
        $action = ProjectAction::factory()->create([
            'status' => ProjectAction::STATUS_COMPLETED,
        ]);

        $action->verify();

        $this->assertDatabaseHas('project_action_events', [
            'project_action_id' => $action->id,
            'type' => 'verified',
        ]);
    }
}
