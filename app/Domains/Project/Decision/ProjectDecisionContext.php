<?php

namespace App\Domains\Project\Decision;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ProjectDecisionContext
{
    public function __construct(
        public ProjectDecisionRequest $request,
        public int $projectId,
        public string $projectName,
        public string $projectStatus,
        public int $openCriticalRiskCount,
        public int $openHighRiskCount,
        public int $overdueDeliverableCount,
        public ?CarbonImmutable $latestUpdateAt,
        public int $updatesWithBlockersCount,
        public int $updatesWithRisksCount,
        public int $openUpdateRequestCount,
        public int $respondedUpdateRequestCount,
        public int $clientCommitmentCount,
        public CarbonImmutable $observedAt,
    ) {
        if ($this->projectId !== $this->request->projectId) {
            throw new InvalidArgumentException(
                'Project decision context facts must belong to the requested project.'
            );
        }

        if (trim($this->projectName) === '') {
            throw new InvalidArgumentException(
                'Project decision context project name cannot be empty.'
            );
        }

        if (trim($this->projectStatus) === '') {
            throw new InvalidArgumentException(
                'Project decision context project status cannot be empty.'
            );
        }

        $counts = [
            'open critical risk count' => $this->openCriticalRiskCount,
            'open high risk count' => $this->openHighRiskCount,
            'overdue deliverable count' => $this->overdueDeliverableCount,
            'updates with blockers count' => $this->updatesWithBlockersCount,
            'updates with risks count' => $this->updatesWithRisksCount,
            'open update request count' => $this->openUpdateRequestCount,
            'responded update request count' => $this->respondedUpdateRequestCount,
            'client commitment count' => $this->clientCommitmentCount,
        ];

        foreach ($counts as $name => $count) {
            if ($count < 0) {
                throw new InvalidArgumentException(
                    "Project decision context {$name} cannot be negative."
                );
            }
        }
    }
}
