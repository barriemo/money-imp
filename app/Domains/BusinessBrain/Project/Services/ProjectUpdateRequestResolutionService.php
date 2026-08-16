<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Models\ProjectUpdateRequest;
use Carbon\CarbonImmutable;

class ProjectUpdateRequestResolutionService
{
    public function resolveFromUpdates(): array
    {
        $resolved = [];

        $requests =
            ProjectUpdateRequest::query()
                ->where(
                    'status',
                    'open'
                )
                ->with(
                    'project.updates'
                )
                ->get();

        foreach (
            $requests as $request
        ) {
            $latestUpdate =
                $request
                    ->project
                    ->updates()
                    ->latest('created_at')
                    ->first();

            if (
                ! $latestUpdate
            ) {
                continue;
            }

            if (
                $latestUpdate->created_at->lte(
                    $request->created_at
                )
            ) {
                continue;
            }

            $request->update([
                'status' => 'responded',

                'response' => $latestUpdate->summary,

                'responded_at' => CarbonImmutable::now(),
            ]);

            $resolved[] = $request;
        }

        return $resolved;
    }
}
