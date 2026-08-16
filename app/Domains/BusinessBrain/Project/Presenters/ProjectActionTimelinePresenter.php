<?php

namespace App\Domains\BusinessBrain\Project\Presenters;

use App\Models\ProjectAction;

class ProjectActionTimelinePresenter
{
    public function present(ProjectAction $action): array
    {
        return [
            'action' => $action->action,
            'status' => $action->status,
            'timeline' => $action->events
                ->sortBy('created_at')
                ->map(function ($event) {
                    return [
                        'type' => $event->type,
                        'payload' => $event->payload,
                        'occurred_at' => $event->created_at,
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
