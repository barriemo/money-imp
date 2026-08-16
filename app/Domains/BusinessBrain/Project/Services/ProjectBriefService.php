<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Domains\BusinessBrain\Project\Briefing\ProjectBrief;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class ProjectBriefService
{
    public function __construct(
        private ProjectHealthService $health,

        private DeliverableHealthService $deliverableHealth
    ) {}

    public function current(): ProjectBrief
    {
        if (
            ! Schema::hasTable('projects')
        ) {
            return new ProjectBrief(
                activeProjects: 0,

                blockedProjects: 0,

                atRiskProjects: 0,

                priorityProjects: [],

                risks: [],

                overdueDeliverables: [],

                updateRequests: [],

                asOf: CarbonImmutable::now()
            );
        }

        $projects =
            Project::query()
                ->with([
                    'risks',
                    'updates',
                    'deliverables',
                ])
                ->where(
                    'status',
                    'active'
                )
                ->get();

        $overdueDeliverables =
            $projects
                ->flatMap(
                    function (Project $project) {
                        return $project
                            ->deliverables
                            ->map(
                                function ($deliverable) use ($project) {
                                    $health =
                                        $this->deliverableHealth
                                            ->assess(
                                                $deliverable
                                            );

                                    if (
                                        $health->status !== 'overdue'
                                    ) {
                                        return null;
                                    }

                                    return [
                                        'project' => $project->name,

                                        'deliverable' => $deliverable->name,

                                        'owner' => $deliverable->owner,

                                        'due_date' => $deliverable->due_date,
                                    ];
                                }
                            );
                    }
                )
                ->filter()
                ->values()
                ->all();

        $assessed =
            $projects->map(
                function (Project $project) {
                    return [
                        'project' => $project,

                        'assessment' => $this->health
                            ->assess(
                                $project
                            ),
                    ];
                }
            );

        return new ProjectBrief(
            activeProjects: $projects->count(),

            blockedProjects: $assessed
                ->filter(
                    fn ($item) => $item['assessment']->status === 'blocked'
                )
                ->count(),

            atRiskProjects: $assessed
                ->filter(
                    fn ($item) => $item['assessment']->status === 'at_risk'
                )
                ->count(),

            priorityProjects: $assessed
                ->filter(
                    fn ($item) => $item['assessment']
                        ->requiresAttention()
                )
                ->map(
                    fn ($item) => [
                        'name' => $item['project']->name,

                        'health' => $item['assessment']->status,

                        'reasons' => $item['assessment']->reasons,

                        'recommended_action' => $item['assessment']
                            ->recommendedAction,
                    ]
                )
                ->values()
                ->all(),

            risks: $projects
                ->flatMap(
                    fn ($project) => $project->risks
                )
                ->values()
                ->all(),

            overdueDeliverables: $overdueDeliverables,

            updateRequests: $projects
                ->flatMap(
                    function (Project $project) {
                        return $project
                            ->updateRequests
                            ->where(
                                'status',
                                'open'
                            )
                            ->map(
                                function ($request) use ($project) {
                                    return [
                                        'project' => $project->name,

                                        'requested_from' => $request->requested_from,

                                        'reason' => $request->reason,

                                        'requested_at' => $request->requested_at,
                                    ];
                                }
                            );
                    }
                )
                ->values()
                ->all(),

            asOf: CarbonImmutable::now()
        );
    }
}
