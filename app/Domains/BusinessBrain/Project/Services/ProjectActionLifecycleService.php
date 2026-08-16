<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Models\ProjectAction;
use DomainException;

class ProjectActionLifecycleService
{
    public function approve(ProjectAction $action): void
    {
        $this->transition(
            $action,
            ProjectAction::STATUS_APPROVED,
            'approved'
        );
    }

    public function assign(
        ProjectAction $action,
        string $owner
    ): void {
        if (! in_array($action->status, [
            ProjectAction::STATUS_OPEN,
            ProjectAction::STATUS_APPROVED,
        ])) {
            throw new DomainException(
                'Only open or approved actions can be assigned.'
            );
        }

        $action->assigned_to = $owner;
        $action->status = ProjectAction::STATUS_ASSIGNED;
        $action->save();

        $this->recordEvent(
            $action,
            'assigned',
            [
                'owner' => $owner,
            ]
        );
    }

    public function start(ProjectAction $action): void
    {
        $this->transition(
            $action,
            ProjectAction::STATUS_IN_PROGRESS,
            'started'
        );
    }

    public function complete(ProjectAction $action): void
    {
        $action->status = ProjectAction::STATUS_COMPLETED;
        $action->completed_at = now();
        $action->save();

        $this->recordEvent(
            $action,
            'completed'
        );
    }

    public function verify(ProjectAction $action): void
    {
        if ($action->status !== ProjectAction::STATUS_COMPLETED) {
            throw new DomainException(
                'Only completed actions can be verified.'
            );
        }

        $action->status = ProjectAction::STATUS_VERIFIED;
        $action->verified_at = now();
        $action->save();

        $this->recordEvent(
            $action,
            'verified'
        );
    }

    protected function transition(
        ProjectAction $action,
        string $status,
        string $event
    ): void {
        $action->status = $status;
        $action->save();

        $this->recordEvent(
            $action,
            $event
        );
    }

    protected function recordEvent(
        ProjectAction $action,
        string $type,
        array $payload = []
    ): void {
        $action->events()->create([
            'type' => $type,
            'payload' => $payload,
        ]);
    }
}
