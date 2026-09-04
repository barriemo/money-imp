<?php

namespace App\Domains\Cfo\Decision;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttentionPolicy;
use App\Domains\BusinessBrain\BusinessState\BusinessStateService;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineFactory;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineSnapshotRepository;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeDetector;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidenceService;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationPolicy;

class CfoDecisionContextService
{
    public function __construct(
        private BusinessStateService $states,
        private BusinessStateBaselineFactory $factory,
        private BusinessStateBaselineSnapshotRepository $repository,
        private BusinessStateChangeDetector $detector,
        private BusinessStateChangeAttentionPolicy $attention,
        private BusinessStateExplanationEvidenceService $explanationEvidence,
        private BusinessStateExplanationPolicy $explanationPolicy,
    ) {}

    public function forDecision(
        CfoDecisionRequest $request
    ): CfoDecisionContext {
        /*
         * Build exactly one current BusinessState.
         *
         * State, change, attention and explanation must all describe the
         * same temporal observation before any CFO recommendation policy
         * is allowed to consume this context.
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
            return new CfoDecisionContext(
                request: $request,

                state: $state,

                current: $current,

                previous: null,

                changes: collect(),

                attention: collect(),

                explanations: collect()
            );
        }

        $changes =
            $this->detector
                ->compare(
                    previous: $previous,

                    current: $current
                );

        $attention =
            $this->attention
                ->assess(
                    $changes
                );

        $explanations =
            $changes
                ->map(
                    fn (BusinessStateChange $change): BusinessStateExplanation => $this->explanationPolicy
                        ->assess(
                            $this->explanationEvidence
                                ->forChange(
                                    change: $change,

                                    state: $state
                                )
                        )
                )
                ->values();

        return new CfoDecisionContext(
            request: $request,

            state: $state,

            current: $current,

            previous: $previous,

            changes: $changes,

            attention: $attention,

            explanations: $explanations
        );
    }
}
