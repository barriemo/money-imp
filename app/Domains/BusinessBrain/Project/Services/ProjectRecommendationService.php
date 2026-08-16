<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Domains\BusinessBrain\Project\Recommendations\ProjectRecommendation;
use App\Models\Project;
use Illuminate\Support\Collection;

class ProjectRecommendationService
{
    public function for(
        Project $project
    ): Collection {
        $recommendations = collect();

        if (
            $project->risks()
                ->where(
                    'status',
                    'open'
                )
                ->whereIn(
                    'severity',
                    [
                        'high',
                        'critical',
                    ]
                )
                ->exists()
        ) {
            $recommendations->push(
                new ProjectRecommendation(
                    project: $project->name,

                    priority: 'high',

                    reason: 'High priority project risk exists.',

                    action: 'Escalate unresolved project risk.'
                )
            );
        }

        if (
            $project->updateRequests()
                ->where(
                    'status',
                    'open'
                )
                ->exists()
        ) {
            $recommendations->push(
                new ProjectRecommendation(
                    project: $project->name,

                    priority: 'medium',

                    reason: 'Project update is outstanding.',

                    action: 'Request project progress update.'
                )
            );
        }

        if (
            $project->deliverables()
                ->whereNull(
                    'completed_at'
                )
                ->where(
                    'due_date',
                    '<',
                    now()
                )
                ->exists()
        ) {
            $recommendations->push(
                new ProjectRecommendation(
                    project: $project->name,

                    priority: 'high',

                    reason: 'Overdue deliverable exists.',

                    action: 'Review overdue delivery ownership.'
                )
            );
        }

        return $recommendations
            ->sortBy(
                fn (ProjectRecommendation $recommendation) => $this->priorityWeight(
                    $recommendation->priority
                )
            )
            ->values();
    }

    private function priorityWeight(
        string $priority
    ): int {
        return match ($priority) {
            'critical' => 1,

            'high' => 2,

            'medium' => 3,

            'low' => 4,

            default => 5,
        };
    }
}
