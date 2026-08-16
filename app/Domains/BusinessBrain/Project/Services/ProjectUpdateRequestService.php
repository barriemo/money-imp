<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Models\Project;
use App\Models\ProjectUpdateRequest;
use Carbon\CarbonImmutable;

class ProjectUpdateRequestService
{
    public function generate(): array
    {
        $created = [];

        $projects =
            Project::query()
                ->with([
                    'updates',
                    'updateRequests',
                ])
                ->where(
                    'status',
                    'active'
                )
                ->get();

        foreach ($projects as $project) {
            $latestUpdate =
                $project->updates
                    ->sortByDesc('created_at')
                    ->first();

            if (
                $latestUpdate
                && $latestUpdate->created_at
                    >= now()->subDays(14)
            ) {
                continue;
            }

            $exists =
                $project->updateRequests()
                    ->where(
                        'status',
                        'open'
                    )
                    ->exists();

            if ($exists) {
                continue;
            }

            $created[] =
                ProjectUpdateRequest::create([
                    'project_id' => $project->id,

                    'requested_from' => $latestUpdate?->submitted_by,

                    'reason' => 'No recent project progress update received.',

                    'status' => 'open',

                    'requested_at' => CarbonImmutable::now(),
                ]);
        }

        return $created;
    }
}
