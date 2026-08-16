<?php

namespace App\Domains\BusinessBrain\Project\Health;

use App\Models\Project;

class ProjectCommitmentRiskDetector
{
    public function hasClientCommitmentRisk(
        Project $project
    ): bool {
        $hasClientCommitment =
            $project
                ->communications()
                ->where(
                    'direction',
                    'client'
                )
                ->whereNotNull(
                    'commitment'
                )
                ->exists();

        $hasOverdueDeliverable =
            $project
                ->deliverables()
                ->whereDate(
                    'due_date',
                    '<',
                    now()
                )
                ->whereNull(
                    'completed_at'
                )
                ->exists();

        return $hasClientCommitment
            && $hasOverdueDeliverable;
    }
}
