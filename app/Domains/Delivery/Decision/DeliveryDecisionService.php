<?php

namespace App\Domains\Delivery\Decision;

use InvalidArgumentException;

class DeliveryDecisionService
{
    public function __construct(
        private DeliveryDecisionContextService $contexts,
        private DeliveryEvidenceReviewReadinessPolicy $evidenceReview,
    ) {}

    public function decide(
        DeliveryDecisionRequest $request
    ): DeliveryDecision {
        /*
         * Delivery OS v1 intentionally exposes exactly one
         * authoritative decision policy.
         *
         * Routing is orchestration only. Unsupported delivery
         * decision types fail explicitly rather than falling through
         * to generic reasoning.
         */
        if (
            ! $this->evidenceReview
                ->supports(
                    $request
                )
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Delivery OS v1 has no authoritative policy for decision request %s.',
                    $request->key
                )
            );
        }

        $context =
            $this->contexts
                ->forDecision(
                    $request
                );

        return $this->evidenceReview
            ->decide(
                $context
            );
    }
}
