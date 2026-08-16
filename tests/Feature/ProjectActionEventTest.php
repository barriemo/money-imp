<?php

namespace Tests\Feature;

use App\Models\ProjectAction;
use App\Models\ProjectActionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_can_have_events(): void
    {
        $action = ProjectAction::factory()->create();

        $event = ProjectActionEvent::factory()->create([
            'project_action_id' => $action->id,
        ]);

        $this->assertTrue(
            $action->events->contains($event)
        );
    }

    public function test_event_belongs_to_action(): void
    {
        $action = ProjectAction::factory()->create();

        $event = ProjectActionEvent::factory()->create([
            'project_action_id' => $action->id,
        ]);

        $this->assertSame(
            $action->id,
            $event->projectAction->id
        );
    }

    public function test_event_stores_payload(): void
    {
        $event = ProjectActionEvent::factory()->create([
            'type' => 'assigned',
            'payload' => [
                'owner' => 'delivery_imp',
            ],
        ]);

        $this->assertSame(
            'delivery_imp',
            $event->payload['owner']
        );
    }

    public function test_action_can_have_multiple_events(): void
    {
        $action = ProjectAction::factory()->create();

        ProjectActionEvent::factory()
            ->count(3)
            ->create([
                'project_action_id' => $action->id,
            ]);

        $this->assertCount(
            3,
            $action->events
        );
    }
}
