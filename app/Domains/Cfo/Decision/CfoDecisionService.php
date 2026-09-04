<?php

namespace App\Domains\Cfo\Decision;

use InvalidArgumentException;

class CfoDecisionService
{
    public function __construct(
        private CfoDecisionContextService $contexts,
        private DiscretionarySpendDecisionPolicy $discretionarySpend,
    ) {}

    public function decide(
        CfoDecisionRequest $request
    ): CfoDecision {
        /*
         * CFO v1 intentionally exposes exactly one decision policy.
         *
         * Routing is orchestration only. Unsupported decision types must
         * fail explicitly rather than falling through to generic reasoning.
         */
        if (
            ! $this->discretionarySpend
                ->supports(
                    $request
                )
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'CFO v1 has no authoritative policy for decision request %s.',
                    $request->key
                )
            );
        }

        $context =
            $this->contexts
                ->forDecision(
                    $request
                );

        return $this->discretionarySpend
            ->decide(
                $context
            );
    }
}
