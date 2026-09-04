<?php

namespace App\Domains\BusinessBrain\BusinessState\Explanation;

use App\Domains\BusinessBrain\BusinessState\BusinessStateService;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineFactory;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineSnapshotRepository;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeDetector;

class BusinessStateExplanationReportService
{
    public function __construct(
        private BusinessStateService $states,
        private BusinessStateBaselineFactory $factory,
        private BusinessStateBaselineSnapshotRepository $repository,
        private BusinessStateChangeDetector $detector,
        private BusinessStateExplanationEvidenceService $evidence,
        private BusinessStateExplanationPolicy $policy,
    ) {}

    public function current(): BusinessStateExplanationReport
    {
        /*
         * Build exactly one current BusinessState.
         *
         * Its timestamp drives both the comparison baseline and all
         * explanation evidence so evidence can never be attached to a
         * different current-state observation.
         */
        $state =
            $this->states
                ->current();

        $current =
            $this->factory
                ->fromState(
                    $state
                );

        $previous =
            $this->repository
                ->latestBefore(
                    $current->asOf
                );

        if ($previous === null) {
            return new BusinessStateExplanationReport(
                current: $current,

                previous: null,

                explanations: collect()
            );
        }

        $changes =
            $this->detector
                ->compare(
                    previous: $previous,

                    current: $current
                );

        $explanations =
            $changes
                ->map(
                    fn (BusinessStateChange $change): BusinessStateExplanation => $this->policy
                        ->assess(
                            $this->evidence
                                ->forChange(
                                    change: $change,

                                    state: $state
                                )
                        )
                )
                ->values();

        return new BusinessStateExplanationReport(
            current: $current,

            previous: $previous,

            explanations: $explanations
        );
    }
}
