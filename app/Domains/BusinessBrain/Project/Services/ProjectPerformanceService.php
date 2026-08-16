<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Domains\BusinessBrain\Project\Performance\ProjectPerformance;
use App\Models\ProjectUpdateRequest;
use Illuminate\Support\Facades\Schema;

class ProjectPerformanceService
{
    public function current(): ProjectPerformance
    {
        if (
            ! Schema::hasTable('project_update_requests')
        ) {
            return new ProjectPerformance(
                openUpdateRequests: 0,

                resolvedUpdateRequests: 0,

                averageResponseDays: null,

                slowRespondingProjects: []
            );
        }

        $requests =
            ProjectUpdateRequest::query()
                ->with('project')
                ->get();

        $open =
            $requests
                ->where(
                    'status',
                    'open'
                )
                ->count();

        $resolved =
            $requests
                ->where(
                    'status',
                    'responded'
                )
                ->count();

        $responseTimes =
            $requests
                ->whereNotNull(
                    'responded_at'
                )
                ->map(
                    function ($request) {
                        return $request
                            ->created_at
                            ->diffInDays(
                                $request->responded_at
                            );
                    }
                );

        $average =
            $responseTimes->count() > 0
                ? round(
                    $responseTimes->average(),
                    1
                )
                : null;

        $slowProjects =
            $requests
                ->where(
                    'status',
                    'responded'
                )
                ->groupBy(
                    fn ($request) => $request->project->name
                )
                ->map(
                    fn ($items, $name) => [
                        'project' => $name,

                        'requests' => $items->count(),

                        'average_days' => round(
                            $items
                                ->map(
                                    fn ($item) => $item->created_at
                                        ->diffInDays(
                                            $item->responded_at
                                        )
                                )
                                ->average(),
                            1
                        ),
                    ]
                )
                ->sortByDesc(
                    'average_days'
                )
                ->values()
                ->take(5)
                ->all();

        return new ProjectPerformance(
            openUpdateRequests: $open,

            resolvedUpdateRequests: $resolved,

            averageResponseDays: $average,

            slowRespondingProjects: $slowProjects
        );
    }
}
