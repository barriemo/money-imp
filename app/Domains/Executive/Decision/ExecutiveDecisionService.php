<?php

namespace App\Domains\Executive\Decision;

use InvalidArgumentException;

class ExecutiveDecisionService
{
    public function __construct(
        private ExecutiveDecisionContextService $contexts,
        private ManagementResponseReadinessPolicy $managementResponseReadiness,
    ) {}

    public function decide(
        ExecutiveDecisionRequest $request
    ): ExecutiveDecision {
        /*
         * Executive OS v1 intentionally exposes exactly one
         * authoritative decision policy.
         *
         * Routing is orchestration only. Unsupported Executive
         * decision types fail explicitly before context assembly.
         */
        if (
            ! $this->managementResponseReadiness
                ->supports(
                    $request
                )
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Executive OS v1 has no authoritative policy for decision request %s.',
                    $request->key
                )
            );
        }

        $context =
            $this->contexts
                ->forDecision(
                    $request
                );

        return $this->managementResponseReadiness
            ->decide(
                $context
            );
    }
}
