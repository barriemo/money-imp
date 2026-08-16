<?php

namespace App\Domains\BusinessBrain\Project\Presenters;

use App\Models\ProjectActionOutcome;

class ProjectActionOutcomePresenter
{
    public function present(ProjectActionOutcome $outcome): array
    {
        return [
            'type' => $outcome->type,
            'description' => $outcome->description,
            'metric' => $outcome->metric,
            'value' => $outcome->value,
            'confidence' => $outcome->confidence,
            'metadata' => $outcome->metadata,
        ];
    }
}
