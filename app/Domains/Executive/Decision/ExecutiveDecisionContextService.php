<?php

namespace App\Domains\Executive\Decision;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttentionPolicy;
use App\Domains\BusinessBrain\BusinessState\BusinessStateService;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineFactory;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineSnapshotRepository;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeDetector;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidenceService;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationPolicy;
use App\Domains\Cfo\Decision\CfoDecisionService;
use App\Domains\Commercial\Decision\CommercialDecisionService;
use App\Domains\Delivery\Decision\DeliveryDecisionService;

class ExecutiveDecisionContextService
{
    public function __construct(
        private BusinessStateService $states,
        private BusinessStateBaselineFactory $factory,
        private BusinessStateBaselineSnapshotRepository $repository,
        private BusinessStateChangeDetector $detector,
        private BusinessStateChangeAttentionPolicy $attention,
        private BusinessStateExplanationEvidenceService $explanationEvidence,
        private BusinessStateExplanationPolicy $explanationPolicy,
        private CfoDecisionService $cfo,
        private CommercialDecisionService $commercial,
        private DeliveryDecisionService $delivery,
    ) {}

    public function forDecision(
        ExecutiveDecisionRequest $request
    ): ExecutiveDecisionContext {
        /*
         * Executive context starts with exactly one current BusinessState.
         *
         * State, baseline, change, attention and explanation therefore
         * describe one coherent Business Brain observation.
         *
         * Specialist decisions are obtained only through their public
         * decision services. Their own as-of timestamps are preserved;
         * Executive does not manufacture a shared timestamp or recompute
         * specialist truth.
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

        $changes =
            collect();

        $attention =
            collect();

        $explanations =
            collect();

        if ($previous !== null) {
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
        }

        $cfoDecision =
            $request->cfoRequest === null
                ? null
                : $this->cfo
                    ->decide(
                        $request->cfoRequest
                    );

        $commercialDecision =
            $request->commercialRequest === null
                ? null
                : $this->commercial
                    ->decide(
                        $request->commercialRequest
                    );

        $deliveryDecision =
            $request->deliveryRequest === null
                ? null
                : $this->delivery
                    ->decide(
                        $request->deliveryRequest
                    );

        return new ExecutiveDecisionContext(
            request: $request,

            state: $state,

            current: $current,

            previous: $previous,

            changes: $changes,

            attention: $attention,

            explanations: $explanations,

            cfoDecision: $cfoDecision,

            commercialDecision: $commercialDecision,

            deliveryDecision: $deliveryDecision
        );
    }
}
