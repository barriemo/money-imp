<?php

namespace App\Domains\BusinessBrain\Project\Health;

use App\Models\Project;

class ProjectUpdateHealthDetector
{
    public function needsUpdate(
        Project $project
    ): bool {
        $latest =
            $project
                ->updates()
                ->latest()
                ->first();

        if (! $latest) {
            return true;
        }

        return $latest->created_at
            ->lt(
                now()->subDays(14)
            );
    }

    public function hasBlockedUpdate(
        Project $project
    ): bool {
        return $project
            ->updates()
            ->where(function ($query) {
                $query
                    ->whereNotNull(
                        'blockers'
                    )
                    ->orWhereNotNull(
                        'risks'
                    );
            })
            ->exists();
    }
}
