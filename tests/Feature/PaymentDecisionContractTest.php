<?php

namespace Tests\Feature;

use App\Domains\Payment\Decision\PaymentDecision;
use App\Domains\Payment\Decision\PaymentDecisionConstraint;
use App\Domains\Payment\Decision\PaymentDecisionEvidence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

class PaymentDecisionContractTest extends TestCase
{
    public function test_recommended_decision_requires_real_support_and_no_unresolved_uncertainty(): void
    {
        $decision =
            new PaymentDecision(
                key: 'example',

                question: 'Can this payment-evidence conclusion be established?',

                status: PaymentDecision::RECOMMENDED,

                recommendation: 'The payment-evidence conclusion is established from recorded evidence.',

                rationale: 'The required payment evidence is established.',

                evidence: collect([
                    $this->support(
                        confidence: 90
                    ),
                    $this->context(),
                ]),

                constraints: collect(),

                confidence: 90,

                missingTruth: collect(),

                asOf: $this->asOf()
            );

        $this->assertSame(
            PaymentDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            90,
            $decision->confidence
        );
    }

    public function test_recommended_can_represent_established_negative_payment_evidence_conclusion(): void
    {
        $decision =
            new PaymentDecision(
                key: 'example',

                question: 'Does the available evidence support a payment candidate?',

                status: PaymentDecision::RECOMMENDED,

                recommendation: 'Available evidence does not support a payment candidate for this exact client.',

                rationale: 'The bounded evidence search is complete enough for that limited conclusion.',

                evidence: collect([
                    $this->support(),
                ]),

                constraints: collect(),

                confidence: 100,

                missingTruth: collect(),

                asOf: $this->asOf()
            );

        $this->assertSame(
            PaymentDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertStringStartsWith(
            'Available evidence does not support',
            $decision->recommendation
        );
    }

    public function test_context_does_not_count_as_support(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Recommended or conditional payment decisions require supporting evidence.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::RECOMMENDED,

            recommendation: 'Proceed with the bounded conclusion.',

            rationale: 'Context exists.',

            evidence: collect([
                $this->context(),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_recommendation_confidence_cannot_exceed_weakest_support(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Payment decision confidence cannot exceed the weakest supporting evidence.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::RECOMMENDED,

            recommendation: 'The bounded conclusion is established.',

            rationale: 'Several evidence sources support the conclusion.',

            evidence: collect([
                $this->support(
                    confidence: 100,
                    source: 'strong_payment_truth'
                ),
                $this->support(
                    confidence: 40,
                    source: 'weak_payment_truth'
                ),
            ]),

            constraints: collect(),

            confidence: 41,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_recommended_decision_cannot_contain_contradictory_evidence(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Recommended payment decisions cannot contain contradictory evidence.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::RECOMMENDED,

            recommendation: 'The bounded conclusion is established.',

            rationale: 'Conflicting evidence remains.',

            evidence: collect([
                $this->support(),
                $this->contradiction(),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_recommended_decision_cannot_contain_constraints(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Recommended payment decisions cannot contain unresolved constraints.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::RECOMMENDED,

            recommendation: 'The bounded conclusion is established.',

            rationale: 'A condition remains.',

            evidence: collect([
                $this->support(),
            ]),

            constraints: collect([
                $this->condition(),
            ]),

            confidence: 100,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_recommended_decision_cannot_contain_missing_truth(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Recommended payment decisions cannot contain missing truth.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::RECOMMENDED,

            recommendation: 'The bounded conclusion is established.',

            rationale: 'Required truth is incomplete.',

            evidence: collect([
                $this->support(),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect([
                'Required payment evidence is incomplete.',
            ]),

            asOf: $this->asOf()
        );
    }

    public function test_conditional_decision_requires_support_and_explicit_uncertainty(): void
    {
        $decision =
            new PaymentDecision(
                key: 'example',

                question: 'Can this payment-evidence conclusion be established?',

                status: PaymentDecision::CONDITIONAL,

                recommendation: 'Use the bounded payment-evidence conclusion only with the stated uncertainty preserved.',

                rationale: 'Supporting evidence exists but an explicit evidence condition remains.',

                evidence: collect([
                    $this->support(
                        confidence: 80
                    ),
                ]),

                constraints: collect([
                    $this->condition(),
                ]),

                confidence: 80,

                missingTruth: collect(),

                asOf: $this->asOf()
            );

        $this->assertSame(
            PaymentDecision::CONDITIONAL,
            $decision->status
        );
    }

    public function test_conditional_decision_cannot_contain_blocking_constraint(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Conditional payment decisions cannot contain blocking constraints.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::CONDITIONAL,

            recommendation: 'Use the bounded conclusion conditionally.',

            rationale: 'A blocker remains.',

            evidence: collect([
                $this->support(),
            ]),

            constraints: collect([
                $this->blocker(),
            ]),

            confidence: 100,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_conditional_decision_cannot_exist_without_explicit_uncertainty(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Conditional payment decisions require explicit uncertainty.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::CONDITIONAL,

            recommendation: 'Use the bounded conclusion conditionally.',

            rationale: 'Nothing actually remains uncertain.',

            evidence: collect([
                $this->support(),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_deferred_decision_has_no_recommendation_and_zero_recommendation_confidence(): void
    {
        $decision =
            new PaymentDecision(
                key: 'example',

                question: 'Can this payment-evidence conclusion be established?',

                status: PaymentDecision::DEFERRED,

                recommendation: null,

                rationale: 'Required payment truth is not established.',

                evidence: collect([
                    $this->context(),
                ]),

                constraints: collect([
                    $this->blocker(),
                ]),

                confidence: 0,

                missingTruth: collect([
                    'Required payment evidence is not established.',
                ]),

                asOf: $this->asOf()
            );

        $this->assertSame(
            PaymentDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertSame(
            0,
            $decision->confidence
        );
    }

    public function test_deferred_decision_requires_blocker_or_missing_truth(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Deferred payment decisions require a blocking constraint or explicit missing truth.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::DEFERRED,

            recommendation: null,

            rationale: 'Nothing blocks the decision.',

            evidence: collect(),

            constraints: collect(),

            confidence: 0,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_deferred_decision_cannot_contain_recommendation(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Deferred payment decisions cannot contain a recommendation.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::DEFERRED,

            recommendation: 'The bounded conclusion is established.',

            rationale: 'Required truth is missing.',

            evidence: collect(),

            constraints: collect([
                $this->blocker(),
            ]),

            confidence: 0,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_deferred_decision_confidence_must_be_zero(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Deferred payment decisions must have recommendation confidence 0.'
        );

        new PaymentDecision(
            key: 'example',

            question: 'Can this payment-evidence conclusion be established?',

            status: PaymentDecision::DEFERRED,

            recommendation: null,

            rationale: 'The blocker may be certain, but no recommendation is established.',

            evidence: collect([
                $this->context(),
            ]),

            constraints: collect([
                $this->blocker(
                    confidence: 100
                ),
            ]),

            confidence: 100,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_evidence_and_constraints_validate_their_own_contracts(): void
    {
        $this->assertSame(
            [
                'supports',
                'contradicts',
                'context',
            ],
            PaymentDecisionEvidence::POSITIONS
        );

        $this->assertSame(
            [
                'blocking',
                'condition',
            ],
            PaymentDecisionConstraint::TYPES
        );

        foreach (
            [
                -1,
                101,
            ] as $confidence
        ) {
            try {
                $this->support(
                    confidence: $confidence
                );

                $this->fail(
                    'Invalid evidence confidence was accepted.'
                );
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    'Payment decision evidence confidence must be between 0 and 100.',
                    $exception->getMessage()
                );
            }

            try {
                $this->condition(
                    confidence: $confidence
                );

                $this->fail(
                    'Invalid constraint confidence was accepted.'
                );
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    'Payment decision constraint confidence must be between 0 and 100.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_contract_contains_no_priority_scoring_allocation_approval_collection_execution_or_persistence_state(): void
    {
        foreach (
            [
                PaymentDecision::class,
                PaymentDecisionEvidence::class,
                PaymentDecisionConstraint::class,
            ] as $class
        ) {
            $reflection =
                new ReflectionClass(
                    $class
                );

            foreach (
                [
                    'priority',
                    'score',
                    'urgency',
                    'ranking',
                    'execution',
                    'executedAt',
                    'actionId',
                    'outcomeId',
                    'recommendedAction',
                    'allocationId',
                    'paymentAllocationId',
                    'approvalId',
                    'approvedBy',
                    'approvedAt',
                    'collectionAction',
                    'chaseAction',
                    'clientRank',
                    'riskScore',
                    'attentionScore',
                ] as $forbidden
            ) {
                $this->assertFalse(
                    $reflection->hasProperty(
                        $forbidden
                    )
                );
            }
        }
    }

    private function support(
        int $confidence = 100,
        string $source = 'payment_truth'
    ): PaymentDecisionEvidence {
        return new PaymentDecisionEvidence(
            source: $source,

            description: 'Established payment evidence supports the bounded guidance.',

            position: PaymentDecisionEvidence::SUPPORTS,

            confidence: $confidence
        );
    }

    private function contradiction(
        int $confidence = 100
    ): PaymentDecisionEvidence {
        return new PaymentDecisionEvidence(
            source: 'payment_truth',

            description: 'Established payment evidence contradicts the bounded guidance.',

            position: PaymentDecisionEvidence::CONTRADICTS,

            confidence: $confidence
        );
    }

    private function context(
        int $confidence = 100
    ): PaymentDecisionEvidence {
        return new PaymentDecisionEvidence(
            source: 'payment_context',

            description: 'Payment context is available but does not itself support guidance.',

            position: PaymentDecisionEvidence::CONTEXT,

            confidence: $confidence
        );
    }

    private function condition(
        int $confidence = 100
    ): PaymentDecisionConstraint {
        return new PaymentDecisionConstraint(
            code: 'payment_evidence_condition',

            description: 'An explicit payment-evidence condition remains.',

            type: PaymentDecisionConstraint::CONDITION,

            source: 'payment_truth',

            confidence: $confidence
        );
    }

    private function blocker(
        int $confidence = 100
    ): PaymentDecisionConstraint {
        return new PaymentDecisionConstraint(
            code: 'payment_truth_blocker',

            description: 'Required payment truth is not established.',

            type: PaymentDecisionConstraint::BLOCKING,

            source: 'payment_truth',

            confidence: $confidence
        );
    }

    private function asOf(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            '2026-09-05 12:00:00'
        );
    }
}
