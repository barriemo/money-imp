<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectActionLifecycleService;
use App\Models\ProjectAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_approval_updates_action_and_creates_event(): void
    {
        $action = ProjectAction::factory()->create();

        app(
            ProjectActionLifecycleService::class
        )->approve($action);

        $action->refresh();

        $this->assertSame(
            ProjectAction::STATUS_APPROVED,
            $action->status
        );

        $this->assertDatabaseHas(
            'project_action_events',
            [
                'project_action_id' => $action->id,
                'type' => 'approved',
            ]
        );
    }

    public function test_service_assignment_records_owner(): void
    {
        $action = ProjectAction::factory()->create();

        app(
            ProjectActionLifecycleService::class
        )->assign(
            $action,
            'delivery_imp'
        );

        $action->refresh();

        $this->assertSame(
            ProjectAction::STATUS_ASSIGNED,
            $action->status
        );

        $this->assertSame(
            'delivery_imp',
            $action->assigned_to
        );

        $this->assertDatabaseHas(
            'project_action_events',
            [
                'project_action_id' => $action->id,
                'type' => 'assigned',
            ]
        );
    }
}
