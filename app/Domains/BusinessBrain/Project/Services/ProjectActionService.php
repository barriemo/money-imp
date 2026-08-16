<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Models\Project;
use App\Models\ProjectAction;

class ProjectActionService
{
    public function createFromRecommendations(
        Project $project
    ): array {
        $recommendations =
            app(ProjectRecommendationService::class)
                ->for($project);

        $created = [];

        foreach ($recommendations as $recommendation) {
            $exists =
                $project->actions()
                    ->where(
                        'action',
                        $recommendation->action
                    )
                    ->where(
                        'status',
                        'open'
                    )
                    ->exists();

            if ($exists) {
                continue;
            }

            $created[] =
                ProjectAction::create([
                    'project_id' => $project->id,

                    'action' => $recommendation->action,

                    'priority' => $recommendation->priority,

                    'reason' => $recommendation->reason,

                    'status' => 'open',
                ]);
        }

        return $created;
    }
}
