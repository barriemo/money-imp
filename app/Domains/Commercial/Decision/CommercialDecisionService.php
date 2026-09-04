<?php

namespace App\Domains\Commercial\Decision;

use InvalidArgumentException;

class CommercialDecisionService
{
    public function __construct(
        private CommercialDecisionContextService $contexts,
        private ServiceReconciliationReadinessPolicy $reconciliationReadiness,
    ) {}

    public function decide(
        CommercialDecisionRequest $request
    ): CommercialDecision {
        /*
         * Commercial OS v1 intentionally exposes exactly one
         * authoritative decision policy.
         *
         * Routing is orchestration only. Unsupported commercial
         * decision types fail explicitly rather than falling through
         * to generic reasoning.
         */
        if (
            ! $this->reconciliationReadiness
                ->supports(
                    $request
                )
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Commercial OS v1 has no authoritative policy for decision request %s.',
                    $request->key
                )
            );
        }

        $context =
            $this->contexts
                ->forDecision(
                    $request
                );

        return $this->reconciliationReadiness
            ->decide(
                $context
            );
    }
}
