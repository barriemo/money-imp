<?php

namespace Tests\Feature;

use App\Domains\Billing\Decision\BillingDecision;
use App\Domains\Billing\Decision\BillingDecisionConstraint;
use App\Domains\Billing\Decision\BillingDecisionEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class BillingDecisionContractTest extends TestCase
{
    public function test_recommended_decision_requires_supported_bounded_guidance(): void
    {
        $decision = new BillingDecision(
            key: 'billing-evidence-readiness',
            question: 'Can canonical billing evidence support a bounded billing conclusion for this exact client service now?',
            status: BillingDecision::STATUS_RECOMMENDED,
            recommendation: 'Canonical billing evidence supports a bounded billing conclusion.',
            rationale: 'Current canonical evidence is sufficient for this bounded conclusion.',
            evidence: collect([
                new BillingDecisionEvidence(
                    key: 'canonical-observed-billing',
                    label: 'Canonical observed billing is established.',
                    position: BillingDecisionEvidence::POSITION_SUPPORTS,
                    confidence: 100,
                ),
            ]),
            constraints: collect(),
            confidence: 100,
            missingTruth: collect(),
            asOf: CarbonImmutable::parse(
                '2026-09-05 12:00:00'
            ),
        );

        $this->assertSame(
            BillingDecision::STATUS_RECOMMENDED,
            $decision->status,
        );

        $this->assertSame(
            100,
            $decision->confidence,
        );
    }

    public function test_recommended_decision_rejects_contradictory_evidence(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new BillingDecision(
            key: 'billing-evidence-readiness',
            question: 'Can canonical billing evidence support a bounded billing conclusion for this exact client service now?',
            status: BillingDecision::STATUS_RECOMMENDED,
            recommendation: 'Canonical billing evidence supports a bounded billing conclusion.',
            rationale: 'The evidence is contradictory.',
            evidence: collect([
                new BillingDecisionEvidence(
                    key: 'support',
                    label: 'Supporting evidence.',
                    position: BillingDecisionEvidence::POSITION_SUPPORTS,
                    confidence: 100,
                ),
                new BillingDecisionEvidence(
                    key: 'contradiction',
                    label: 'Contradictory evidence.',
                    position: BillingDecisionEvidence::POSITION_CONTRADICTS,
                    confidence: 100,
                ),
            ]),
            constraints: collect(),
            confidence: 100,
            missingTruth: collect(),
            asOf: CarbonImmutable::now(),
        );
    }

    public function test_conditional_decision_requires_explicit_uncertainty(): void
    {
        $decision = new BillingDecision(
            key: 'billing-evidence-readiness',
            question: 'Can canonical billing evidence support a bounded billing conclusion for this exact client service now?',
            status: BillingDecision::STATUS_CONDITIONAL,
            recommendation: 'Canonical billing evidence supports a conclusion subject to unresolved attribution truth.',
            rationale: 'Supporting evidence exists but uncertainty remains explicit.',
            evidence: collect([
                new BillingDecisionEvidence(
                    key: 'support',
                    label: 'Supporting billing evidence.',
                    position: BillingDecisionEvidence::POSITION_SUPPORTS,
                    confidence: 80,
                ),
            ]),
            constraints: collect([
                new BillingDecisionConstraint(
                    key: 'attribution-unresolved',
                    label: 'Billing attribution remains unresolved.',
                    type: BillingDecisionConstraint::TYPE_CONDITION,
                ),
            ]),
            confidence: 80,
            missingTruth: collect(),
            asOf: CarbonImmutable::now(),
        );

        $this->assertSame(
            BillingDecision::STATUS_CONDITIONAL,
            $decision->status,
        );
    }

    public function test_conditional_decision_rejects_blocking_constraint(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new BillingDecision(
            key: 'billing-evidence-readiness',
            question: 'Can canonical billing evidence support a bounded billing conclusion for this exact client service now?',
            status: BillingDecision::STATUS_CONDITIONAL,
            recommendation: 'Proceed conditionally.',
            rationale: 'A blocker remains.',
            evidence: collect([
                new BillingDecisionEvidence(
                    key: 'support',
                    label: 'Some supporting evidence.',
                    position: BillingDecisionEvidence::POSITION_SUPPORTS,
                    confidence: 80,
                ),
            ]),
            constraints: collect([
                new BillingDecisionConstraint(
                    key: 'blocking',
                    label: 'Required billing truth is missing.',
                    type: BillingDecisionConstraint::TYPE_BLOCKING,
                ),
            ]),
            confidence: 80,
            missingTruth: collect(),
            asOf: CarbonImmutable::now(),
        );
    }

    public function test_deferred_decision_requires_no_recommendation_and_zero_confidence(): void
    {
        $decision = new BillingDecision(
            key: 'billing-evidence-readiness',
            question: 'Can canonical billing evidence support a bounded billing conclusion for this exact client service now?',
            status: BillingDecision::STATUS_DEFERRED,
            recommendation: null,
            rationale: 'Required billing truth is missing.',
            evidence: collect(),
            constraints: collect([
                new BillingDecisionConstraint(
                    key: 'billing-evidence-missing',
                    label: 'Required canonical billing evidence is missing.',
                    type: BillingDecisionConstraint::TYPE_BLOCKING,
                ),
            ]),
            confidence: 0,
            missingTruth: collect([
                'Canonical billing evidence is required.',
            ]),
            asOf: CarbonImmutable::now(),
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertSame(
            0,
            $decision->confidence,
        );
    }

    public function test_decision_confidence_cannot_exceed_weakest_support(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new BillingDecision(
            key: 'billing-evidence-readiness',
            question: 'Can canonical billing evidence support a bounded billing conclusion for this exact client service now?',
            status: BillingDecision::STATUS_CONDITIONAL,
            recommendation: 'Conditional conclusion.',
            rationale: 'Evidence confidence bounds the decision.',
            evidence: collect([
                new BillingDecisionEvidence(
                    key: 'support-a',
                    label: 'Strong support.',
                    position: BillingDecisionEvidence::POSITION_SUPPORTS,
                    confidence: 100,
                ),
                new BillingDecisionEvidence(
                    key: 'support-b',
                    label: 'Weaker support.',
                    position: BillingDecisionEvidence::POSITION_SUPPORTS,
                    confidence: 60,
                ),
            ]),
            constraints: collect([
                new BillingDecisionConstraint(
                    key: 'uncertainty',
                    label: 'Explicit uncertainty remains.',
                    type: BillingDecisionConstraint::TYPE_CONDITION,
                ),
            ]),
            confidence: 80,
            missingTruth: collect(),
            asOf: CarbonImmutable::now(),
        );
    }

    public function test_contract_rejects_non_domain_evidence(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new BillingDecision(
            key: 'billing-evidence-readiness',
            question: 'Can canonical billing evidence support a bounded billing conclusion for this exact client service now?',
            status: BillingDecision::STATUS_RECOMMENDED,
            recommendation: 'Conclusion.',
            rationale: 'Invalid evidence collection.',
            evidence: new Collection([
                'not-domain-evidence',
            ]),
            constraints: collect(),
            confidence: 100,
            missingTruth: collect(),
            asOf: CarbonImmutable::now(),
        );
    }

    public function test_contract_does_not_expose_execution_or_ranking_authority(): void
    {
        $properties = array_map(
            static fn (
                \ReflectionProperty $property
            ): string => $property->getName(),
            (new \ReflectionClass(
                BillingDecision::class
            ))->getProperties(
                \ReflectionProperty::IS_PUBLIC
            ),
        );

        foreach ([
            'priority',
            'score',
            'urgency',
            'ranking',
            'recommendationScore',
            'recommendedAction',
            'invoiceId',
            'invoiceDraftId',
            'draftInvoiceId',
            'sendInvoice',
            'sendInvoiceId',
            'invoiceSendId',
            'freeAgentInvoiceId',
            'billingRunId',
            'clientRank',
            'riskScore',
            'action',
            'actionId',
            'execution',
            'executedAt',
            'outcomeId',
        ] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $properties,
            );
        }
    }
}
