<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Models\ProjectAction;
use App\Models\ProjectActionRecommendation;

class ProjectActionRecommendationEngine
{
    public function generate(
        ProjectAction $action
    ): ProjectActionRecommendation {
        return $action->recommendations()->create([
            'type' => 'next_step',
            'recommendation' => 'Review '.$action->action,
            'expected_impact' => 'Improve business performance',
            'confidence' => 70,
            'status' => 'open',
        ]);
    }
}
