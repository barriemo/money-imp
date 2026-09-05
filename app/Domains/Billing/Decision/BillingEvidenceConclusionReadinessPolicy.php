<?php

namespace App\Domains\Billing\Decision;

use App\Domains\CommercialTruth\DTO\CanonicalServiceObservedBilling;
use InvalidArgumentException;

final class BillingEvidenceConclusionReadinessPolicy
{
    public const KEY =
        'billing-evidence-readiness';

    public function supports(
        BillingDecisionRequest $request
    ): bool {
        return $request->key === self::KEY;
    }

    public function decide(
        BillingDecisionContext $context
    ): BillingDecision {
        if (! $this->supports($context->request)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Billing evidence conclusion readiness policy does not support decision request %s.',
                    $context->request->key
                )
            );
        }

        if ($context->request->parameters !== []) {
            throw new InvalidArgumentException(
                'Billing evidence conclusion readiness policy does not accept parameters.'
            );
        }

        $observed =
            $context->observedBilling;

        if ($observed === null) {
            return $this
                ->recommendNoCanonicalObservedBilling(
                    $context
                );
        }

        $this->assertCanonicalState(
            $observed
        );

        if (
            $observed->recurringEvidence
            && $observed->freshness === 'current'
        ) {
            return $this
                ->recommendCurrentRecurringEvidence(
                    $context,
                    $observed
                );
        }

        if ($observed->recurringEvidence) {
            return $this
                ->recommendConditionalNonCurrentRecurringEvidence(
                    $context,
                    $observed
                );
        }

        return $this
            ->recommendConditionalObservedNonRecurringEvidence(
                $context,
                $observed
            );
    }

    private function recommendNoCanonicalObservedBilling(
        BillingDecisionContext $context
    ): BillingDecision {
        return new BillingDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: BillingDecision::STATUS_RECOMMENDED,

            recommendation: 'Use the bounded conclusion that no canonical observed billing is established for this exact client service in the canonical billing read model.',

            rationale: 'The exact client-service canonical billing read model returned no canonical observed billing. This is a bounded evidence conclusion only. It does not establish that nothing should be billed and does not establish that no billing obligation exists.',

            evidence: collect([
                new BillingDecisionEvidence(
                    key: 'canonical-observed-billing-absence',

                    label: 'The canonical billing read model contains no canonical observed billing for this exact client service.',

                    position: BillingDecisionEvidence::POSITION_SUPPORTS,

                    confidence: 100,

                    metadata: [
                        'client_service_id' => $context->request
                            ->clientServiceId,

                        'client_id' => $context->clientId,

                        'truth_boundary' => $context->truthBoundary,
                    ]
                ),

                $this->contextEvidence(
                    $context
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $context->observedAt
        );
    }

    private function recommendCurrentRecurringEvidence(
        BillingDecisionContext $context,
        CanonicalServiceObservedBilling $observed
    ): BillingDecision {
        return new BillingDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: BillingDecision::STATUS_RECOMMENDED,

            recommendation: sprintf(
                'Use the bounded conclusion that current recurring canonical billing evidence is established for this exact client service; the recorded current monthly equivalent is %.2f.',
                $observed->currentMonthlyEquivalent
            ),

            rationale: 'The canonical billing read model records recurring evidence with an established monthly or annual cadence and classifies that evidence as current. The recorded monthly equivalent describes observed billing evidence only. It is not a statement of contractual obligation, an instruction to invoice, or proof of what should be billed now.',

            evidence: collect([
                $this->observedStateSupport(
                    context: $context,
                    observed: $observed,

                    label: 'Canonical billing evidence establishes current recurring observed billing for this exact client service.'
                ),

                $this->contextEvidence(
                    $context
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $context->observedAt
        );
    }

    private function recommendConditionalNonCurrentRecurringEvidence(
        BillingDecisionContext $context,
        CanonicalServiceObservedBilling $observed
    ): BillingDecision {
        return new BillingDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: BillingDecision::STATUS_CONDITIONAL,

            recommendation: 'Use only the bounded conclusion that recurring canonical billing evidence exists for this exact client service; current recurring billing evidence is not established as of the observation time.',

            rationale: sprintf(
                'The canonical billing read model establishes recurring %s billing evidence, but its freshness is %s rather than current. Historical or non-current recurring evidence does not establish a current billing obligation or what should be invoiced now.',
                $observed->cadence,
                $observed->freshness
            ),

            evidence: collect([
                $this->observedStateSupport(
                    context: $context,
                    observed: $observed,

                    label: 'Canonical billing evidence establishes recurring observed billing, but not current recurring observed billing.'
                ),

                $this->contextEvidence(
                    $context
                ),
            ]),

            constraints: collect([
                new BillingDecisionConstraint(
                    key: 'current-recurring-billing-not-established',

                    label: 'Recurring billing evidence exists, but the canonical evidence is not current.',

                    type: BillingDecisionConstraint::TYPE_CONDITION,

                    metadata: [
                        'client_service_id' => $observed->clientServiceId,

                        'cadence' => $observed->cadence,

                        'freshness' => $observed->freshness,

                        'days_since_last_observation' => $observed
                            ->daysSinceLastObservation,
                    ]
                ),
            ]),

            confidence: 100,

            missingTruth: collect([
                'Whether current recurring billing is supported for this exact client service is not established by the available canonical evidence.',
            ]),

            asOf: $context->observedAt
        );
    }

    private function recommendConditionalObservedNonRecurringEvidence(
        BillingDecisionContext $context,
        CanonicalServiceObservedBilling $observed
    ): BillingDecision {
        return new BillingDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: BillingDecision::STATUS_CONDITIONAL,

            recommendation: 'Use only the bounded conclusion that canonical billing has been observed for this exact client service; recurring billing is not established by the available canonical evidence.',

            rationale: sprintf(
                'Canonical billing observations exist for this exact client service, but the established cadence is %s with cadence confidence %d and the canonical read model does not classify the evidence as recurring. Observed billing does not by itself establish a recurring billing obligation or what should be invoiced now.',
                $observed->cadence,
                $observed->cadenceConfidence
            ),

            evidence: collect([
                $this->observedStateSupport(
                    context: $context,
                    observed: $observed,

                    label: 'Canonical billing has been observed for this exact client service, but recurring billing evidence is not established.'
                ),

                $this->contextEvidence(
                    $context
                ),
            ]),

            constraints: collect([
                new BillingDecisionConstraint(
                    key: 'recurring-billing-not-established',

                    label: 'Canonical billing observations exist, but recurring billing evidence is not established.',

                    type: BillingDecisionConstraint::TYPE_CONDITION,

                    metadata: [
                        'client_service_id' => $observed->clientServiceId,

                        'cadence' => $observed->cadence,

                        'cadence_confidence' => $observed->cadenceConfidence,

                        'freshness' => $observed->freshness,
                    ]
                ),
            ]),

            confidence: 100,

            missingTruth: collect([
                'Whether the observed billing represents recurring billing for this exact client service is not established.',
            ]),

            asOf: $context->observedAt
        );
    }

    private function assertCanonicalState(
        CanonicalServiceObservedBilling $observed
    ): void {
        if ($observed->evidenceCount <= 0) {
            throw new InvalidArgumentException(
                'Billing evidence conclusion readiness policy received canonical observed billing without evidence.'
            );
        }

        if ($observed->invoiceItemIds === []) {
            throw new InvalidArgumentException(
                'Billing evidence conclusion readiness policy received canonical observed billing without invoice-item evidence.'
            );
        }

        if (! in_array(
            $observed->cadence,
            [
                'monthly',
                'annual',
                'one_off',
                'unknown',
            ],
            true
        )) {
            throw new InvalidArgumentException(
                sprintf(
                    'Billing evidence conclusion readiness policy does not recognise billing cadence %s.',
                    $observed->cadence
                )
            );
        }

        if (! in_array(
            $observed->freshness,
            [
                'current',
                'recently_observed',
                'stale',
                'historical',
                'unknown',
            ],
            true
        )) {
            throw new InvalidArgumentException(
                sprintf(
                    'Billing evidence conclusion readiness policy does not recognise billing freshness %s.',
                    $observed->freshness
                )
            );
        }

        if ($observed->recurringEvidence) {
            if (! in_array(
                $observed->cadence,
                [
                    'monthly',
                    'annual',
                ],
                true
            )) {
                throw new InvalidArgumentException(
                    'Billing evidence conclusion readiness policy received recurring evidence without a recurring cadence.'
                );
            }

            if ($observed->cadenceConfidence < 80) {
                throw new InvalidArgumentException(
                    'Billing evidence conclusion readiness policy received recurring evidence below the established cadence-confidence boundary.'
                );
            }

            if (
                $observed->freshness === 'current'
                && $observed
                    ->currentMonthlyEquivalent === null
            ) {
                throw new InvalidArgumentException(
                    'Billing evidence conclusion readiness policy received current recurring evidence without a current monthly equivalent.'
                );
            }

            if (
                $observed->freshness !== 'current'
                && $observed
                    ->currentMonthlyEquivalent !== null
            ) {
                throw new InvalidArgumentException(
                    'Billing evidence conclusion readiness policy received non-current recurring evidence with a current monthly equivalent.'
                );
            }

            return;
        }

        if (
            $observed->currentMonthlyEquivalent !== null
        ) {
            throw new InvalidArgumentException(
                'Billing evidence conclusion readiness policy received non-recurring evidence with a current monthly equivalent.'
            );
        }

        if ($observed->freshness === 'current') {
            throw new InvalidArgumentException(
                'Billing evidence conclusion readiness policy received non-recurring evidence classified as current recurring billing.'
            );
        }
    }

    private function observedStateSupport(
        BillingDecisionContext $context,
        CanonicalServiceObservedBilling $observed,
        string $label,
    ): BillingDecisionEvidence {
        return new BillingDecisionEvidence(
            key: 'canonical-observed-billing-state',

            label: $label,

            position: BillingDecisionEvidence::POSITION_SUPPORTS,

            confidence: 100,

            metadata: [
                'client_service_id' => $observed->clientServiceId,

                'client_id' => $observed->clientId,

                'evidence_count' => $observed->evidenceCount,

                'invoice_item_ids' => $observed->invoiceItemIds,

                'cadence' => $observed->cadence,

                'cadence_confidence' => $observed->cadenceConfidence,

                'freshness' => $observed->freshness,

                'recurring_evidence' => $observed->recurringEvidence,

                'current_monthly_equivalent' => $observed
                    ->currentMonthlyEquivalent,

                'truth_boundary' => $context->truthBoundary,
            ]
        );
    }

    private function contextEvidence(
        BillingDecisionContext $context
    ): BillingDecisionEvidence {
        return new BillingDecisionEvidence(
            key: 'billing-decision-context',

            label: 'Billing OS evaluated the established read-only canonical billing evidence for this exact client service.',

            position: BillingDecisionEvidence::POSITION_CONTEXT,

            confidence: 100,

            metadata: [
                'client_service_id' => $context->request
                    ->clientServiceId,

                'client_id' => $context->clientId,

                'service_name' => $context->serviceName,

                'service_status' => $context->serviceStatus,

                'canonical_observed_billing_present' => $context->observedBilling
                        !== null,

                'truth_boundary' => $context->truthBoundary,

                'observed_at' => $context->observedAt
                    ->toIso8601String(),
            ]
        );
    }
}
