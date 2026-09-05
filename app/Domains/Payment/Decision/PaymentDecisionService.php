<?php

namespace App\Domains\Payment\Decision;

use InvalidArgumentException;

class PaymentDecisionService
{
    public function __construct(
        private PaymentDecisionContextService $contexts,
        private PaymentEvidenceConclusionReadinessPolicy $evidenceConclusion,
    ) {}

    public function decide(
        PaymentDecisionRequest $request
    ): PaymentDecision {
        /*
         * Payment OS V1 exposes exactly one authoritative policy.
         *
         * Unsupported decision types fail before context assembly.
         * This service does not select clients, rank evidence, invoke
         * payment-allocation workflows or provide generic reasoning.
         */
        if (! $this->evidenceConclusion->supports($request)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Payment OS v1 has no authoritative policy for decision request %s.',
                    $request->key
                )
            );
        }

        $context =
            $this->contexts
                ->forDecision(
                    $request
                );

        return $this->evidenceConclusion
            ->decide(
                $context
            );
    }
}
