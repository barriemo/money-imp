<?php

namespace App\Domains\BusinessBrain\Project\Presenters;

use App\Models\ProjectAction;

class ProjectActionPresenter
{
    public function present(ProjectAction $action): array
    {
        return [
            'project' => $action->project?->name,
            'action' => $action->action,
            'priority' => $action->priority,
            'status' => $action->status,
            'owner' => $action->assigned_to,
            'reason' => $action->reason,
            'evidence' => $action->evidence
                ->map(fn ($evidence) => [
                    'type' => $evidence->type,
                    'description' => $evidence->description,
                    'source' => $evidence->source,
                    'confidence' => $evidence->confidence,
                ])
                ->values()
                ->all(),

            'outcomes' => $action->outcomes
                ->map(fn ($outcome) => [
                    'type' => $outcome->type,
                    'description' => $outcome->description,
                    'metric' => $outcome->metric,
                    'value' => $outcome->value,
                    'confidence' => $outcome->confidence,
                ])
                ->values()
                ->all(),
        ];
    }
}
