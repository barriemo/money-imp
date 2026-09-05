<?php

namespace App\Domains\Project\Decision;

use App\Models\Project;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ProjectDecisionContextService
{
    public function forDecision(
        ProjectDecisionRequest $request,
        ?CarbonImmutable $observedAt = null
    ): ProjectDecisionContext {
        $observedAt ??=
            CarbonImmutable::now();

        $project =
            Project::query()
                ->find(
                    $request->projectId
                );

        if ($project === null) {
            throw new InvalidArgumentException(
                'Project decision subject project does not exist.'
            );
        }

        /*
         * Project OS context is intentionally assembled directly
         * from facts attributable to this exact Project record.
         *
         * It does not consume legacy Project interpretation,
         * orchestration or persisted outcome machinery.
         *
         * This layer only reports what is recorded. Interpretation
         * of those facts belongs to the authoritative Project policy.
         */
        $openCriticalRiskCount =
            $project
                ->risks()
                ->where(
                    'status',
                    'open'
                )
                ->where(
                    'severity',
                    'critical'
                )
                ->count();

        $openHighRiskCount =
            $project
                ->risks()
                ->where(
                    'status',
                    'open'
                )
                ->where(
                    'severity',
                    'high'
                )
                ->count();

        $overdueDeliverableCount =
            $project
                ->deliverables()
                ->whereNull(
                    'completed_at'
                )
                ->whereNotNull(
                    'due_date'
                )
                ->whereDate(
                    'due_date',
                    '<',
                    $observedAt->toDateString()
                )
                ->count();

        $latestUpdate =
            $project
                ->updates()
                ->latest(
                    'created_at'
                )
                ->first();

        $latestUpdateAt =
            $latestUpdate === null
                ? null
                : CarbonImmutable::instance(
                    $latestUpdate->created_at
                );

        $updatesWithBlockersCount =
            $project
                ->updates()
                ->whereNotNull(
                    'blockers'
                )
                ->count();

        $updatesWithRisksCount =
            $project
                ->updates()
                ->whereNotNull(
                    'risks'
                )
                ->count();

        $openUpdateRequestCount =
            $project
                ->updateRequests()
                ->where(
                    'status',
                    'open'
                )
                ->count();

        $respondedUpdateRequestCount =
            $project
                ->updateRequests()
                ->where(
                    'status',
                    'responded'
                )
                ->count();

        $clientCommitmentCount =
            $project
                ->communications()
                ->where(
                    'direction',
                    'client'
                )
                ->whereNotNull(
                    'commitment'
                )
                ->count();

        return new ProjectDecisionContext(
            request: $request,

            projectId: (int) $project->getKey(),

            projectName: (string) $project->name,

            projectStatus: (string) $project->status,

            openCriticalRiskCount: $openCriticalRiskCount,

            openHighRiskCount: $openHighRiskCount,

            overdueDeliverableCount: $overdueDeliverableCount,

            latestUpdateAt: $latestUpdateAt,

            updatesWithBlockersCount: $updatesWithBlockersCount,

            updatesWithRisksCount: $updatesWithRisksCount,

            openUpdateRequestCount: $openUpdateRequestCount,

            respondedUpdateRequestCount: $respondedUpdateRequestCount,

            clientCommitmentCount: $clientCommitmentCount,

            observedAt: $observedAt
        );
    }
}
