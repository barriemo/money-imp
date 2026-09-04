<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttentionPolicy;
use App\Domains\BusinessBrain\BusinessState\BusinessStateService;

class BusinessStateChangeReportService
{
    public function __construct(
        private BusinessStateService $states,
        private BusinessStateBaselineFactory $factory,
        private BusinessStateBaselineSnapshotRepository $repository,
        private BusinessStateChangeDetector $detector,
        private BusinessStateChangeAttentionPolicy $attention
    ) {}

    public function current(): BusinessStateChangeReport
    {
        $current =
            $this->factory->fromState(
                $this->states->current()
            );

        $previous =
            $this->repository->latestBefore(
                $current->asOf
            );

        if ($previous === null) {
            return new BusinessStateChangeReport(
                current: $current,

                previous: null,

                changes: collect(),

                attention: collect()
            );
        }

        $changes =
            $this->detector->compare(
                previous: $previous,

                current: $current
            );

        return new BusinessStateChangeReport(
            current: $current,

            previous: $previous,

            changes: $changes,

            attention: $this->attention->assess(
                $changes
            )
        );
    }
}
