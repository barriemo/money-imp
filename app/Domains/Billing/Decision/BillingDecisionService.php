<?php

namespace App\Domains\Billing\Decision;

use InvalidArgumentException;

class BillingDecisionService
{
    public function __construct(
        private BillingDecisionContextService $contexts,
        private BillingEvidenceConclusionReadinessPolicy $evidenceConclusion,
    ) {}

    public function decide(
        BillingDecisionRequest $request
    ): BillingDecision {
        if (! $this->evidenceConclusion->supports($request)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Billing OS v1 has no authoritative policy for decision request %s.',
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
