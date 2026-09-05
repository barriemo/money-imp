<?php

namespace App\Domains\Delivery\Decision;

use InvalidArgumentException;

final class DeliveryEvidenceReviewReadinessPolicy
{
    public const KEY =
        'delivery_evidence_review';

    public function supports(
        DeliveryDecisionRequest $request
    ): bool {
        return $request->key === self::KEY;
    }

    public function decide(
        DeliveryDecisionContext $context
    ): DeliveryDecision {
        if (! $this->supports($context->request)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Delivery evidence review readiness policy does not support decision request %s.',
                    $context->request->key
                )
            );
        }

        /*
         * V1 intentionally has no policy-specific parameters.
         *
         * Unknown future parameters must fail closed rather than being
         * silently ignored and accidentally changing decision semantics.
         */
        if ($context->request->parameters !== []) {
            throw new InvalidArgumentException(
                'Delivery evidence review readiness policy does not accept parameters.'
            );
        }

        if (! $context->hasRecordedDeliveryEvidence) {
            return $this->deferForMissingEvidence(
                $context
            );
        }

        return $this->recommendHumanReview(
            $context
        );
    }

    private function deferForMissingEvidence(
        DeliveryDecisionContext $context
    ): DeliveryDecision {
        /*
         * The empty WorkLog dataset is known.
         *
         * What is NOT known is the underlying delivery activity.
         * Numeric zeroes produced by that empty dataset must therefore
         * never become evidence that delivery itself is zero, healthy,
         * complete, invoiced or commercially resolved.
         */
        return new DeliveryDecision(
            key: $context->request->key,
            question: $context->request->question,
            status: DeliveryDecision::DEFERRED,
            recommendation: null,
            rationale: 'No client-attributable WorkLog-backed delivery evidence is recorded. Delivery OS therefore cannot recommend human review of delivery evidence that is absent.',
            evidence: collect([
                new DeliveryDecisionEvidence(
                    source: 'business_brain.delivery_truth',
                    description: 'Business Brain DeliveryTruth records no WorkLog-backed delivery evidence for this client.',
                    position: DeliveryDecisionEvidence::CONTEXT,
                    confidence: 100,
                    metadata: [
                        'client_id' => $context
                            ->deliveryTruth
                            ->clientId,
                        'work_log_count' => $context
                            ->deliveryTruth
                            ->workLogCount,
                    ]
                ),
            ]),
            constraints: collect([
                new DeliveryDecisionConstraint(
                    code: 'recorded_delivery_evidence_missing',
                    description: 'Recorded client-attributable delivery evidence is missing.',
                    type: DeliveryDecisionConstraint::BLOCKING,
                    source: 'business_brain.delivery_truth',
                    confidence: 100,
                    metadata: [
                        'client_id' => $context
                            ->deliveryTruth
                            ->clientId,
                    ]
                ),
            ]),
            confidence: 0,
            missingTruth: collect([
                'Recorded client-attributable delivery evidence for this client is missing.',
            ]),
            asOf: $context->observedAt
        );
    }

    private function recommendHumanReview(
        DeliveryDecisionContext $context
    ): DeliveryDecision {
        $truth =
            $context->deliveryTruth;

        /*
         * This recommendation is deliberately about reviewing the
         * recorded evidence and nothing beyond it.
         *
         * WorkLog presence does NOT prove:
         *
         * - completed contractual delivery,
         * - project or deliverable health,
         * - recoverable commercial value,
         * - invoice eligibility,
         * - human commercial disposition.
         *
         * The recommendation confidence is 100 because the supporting
         * fact is only the categorical database fact that one or more
         * client-attributable WorkLog records exist.
         *
         * It is NOT derived from invoiceLinkageConfidence or any
         * upstream heuristic confidence.
         */
        return new DeliveryDecision(
            key: $context->request->key,
            question: $context->request->question,
            status: DeliveryDecision::RECOMMENDED,
            recommendation: 'Proceed to human review of the recorded delivery evidence for this client.',
            rationale: 'Client-attributable WorkLog-backed delivery evidence is recorded. That establishes evidence availability for human review, but it does not establish delivery completion, delivery health, recoverability, commercial disposition or invoice readiness.',
            evidence: collect([
                new DeliveryDecisionEvidence(
                    source: 'business_brain.delivery_truth',
                    description: sprintf(
                        'Business Brain DeliveryTruth records %d WorkLog-backed delivery evidence item(s) for this client.',
                        $truth->workLogCount
                    ),
                    position: DeliveryDecisionEvidence::SUPPORTS,
                    confidence: 100,
                    metadata: [
                        'client_id' => $truth->clientId,
                        'work_log_count' => $truth->workLogCount,
                    ]
                ),
            ]),
            constraints: collect(),
            confidence: 100,
            missingTruth: collect(),
            asOf: $context->observedAt
        );
    }
}
