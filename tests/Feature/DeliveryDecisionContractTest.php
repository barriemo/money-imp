<?php

namespace Tests\Feature;

use App\Domains\Delivery\Decision\DeliveryDecision;
use App\Domains\Delivery\Decision\DeliveryDecisionConstraint;
use App\Domains\Delivery\Decision\DeliveryDecisionEvidence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class DeliveryDecisionContractTest extends TestCase
{
    public function test_recommended_decision_requires_real_support_and_no_unresolved_uncertainty(): void
    {
        $decision =
            new DeliveryDecision(
                key: 'example',

                question: 'Is this delivery response defensible?',

                status: DeliveryDecision::RECOMMENDED,

                recommendation: 'The delivery response is defensible from established evidence.',

                rationale: 'The required delivery truth is established.',

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
            DeliveryDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            90,
            $decision->confidence
        );
    }

    public function test_recommended_can_represent_established_do_not_proceed_guidance(): void
    {
        $decision =
            new DeliveryDecision(
                key: 'example',

                question: 'Should this delivery action proceed?',

                status: DeliveryDecision::RECOMMENDED,

                recommendation: 'Do not proceed with the proposed delivery action.',

                rationale: 'Established evidence supports a negative recommendation.',

                evidence: collect([
                    $this->support(),
                ]),

                constraints: collect(),

                confidence: 100,

                missingTruth: collect(),

                asOf: $this->asOf()
            );

        $this->assertSame(
            DeliveryDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertStringStartsWith(
            'Do not proceed',
            $decision->recommendation
        );
    }

    public function test_context_does_not_count_as_support(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Recommended or conditional delivery decisions require supporting evidence.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::RECOMMENDED,

            recommendation: 'Proceed.',

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
            'Delivery decision confidence cannot exceed the weakest supporting evidence.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::RECOMMENDED,

            recommendation: 'Proceed.',

            rationale: 'Several evidence sources support the recommendation.',

            evidence: collect([
                $this->support(
                    confidence: 100,
                    source: 'strong'
                ),
                $this->support(
                    confidence: 40,
                    source: 'weak'
                ),
            ]),

            constraints: collect(),

            /*
             * An average would be 70.
             *
             * The contract must reject even 41 because the weakest
             * support is only 40.
             */
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
            'Recommended delivery decisions cannot contain contradictory evidence.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::RECOMMENDED,

            recommendation: 'Proceed.',

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
            'Recommended delivery decisions cannot contain unresolved constraints.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::RECOMMENDED,

            recommendation: 'Proceed.',

            rationale: 'A condition still remains.',

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
            'Recommended delivery decisions cannot contain missing truth.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::RECOMMENDED,

            recommendation: 'Proceed.',

            rationale: 'Truth is incomplete.',

            evidence: collect([
                $this->support(),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect([
                'Canonical delivery truth is incomplete.',
            ]),

            asOf: $this->asOf()
        );
    }

    public function test_conditional_decision_requires_support_and_explicit_uncertainty(): void
    {
        $decision =
            new DeliveryDecision(
                key: 'example',

                question: 'Is this delivery response defensible?',

                status: DeliveryDecision::CONDITIONAL,

                recommendation: 'Proceed only if the stated condition is established.',

                rationale: 'Supporting evidence exists but an explicit condition remains.',

                evidence: collect([
                    $this->support(
                        confidence: 80
                    ),
                ]),

                constraints: collect([
                    $this->condition(
                        confidence: 100
                    ),
                ]),

                confidence: 80,

                missingTruth: collect(),

                asOf: $this->asOf()
            );

        $this->assertSame(
            DeliveryDecision::CONDITIONAL,
            $decision->status
        );
    }

    public function test_conditional_decision_cannot_contain_blocking_constraint(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Conditional delivery decisions cannot contain blocking constraints.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::CONDITIONAL,

            recommendation: 'Proceed conditionally.',

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
            'Conditional delivery decisions require explicit uncertainty.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::CONDITIONAL,

            recommendation: 'Proceed conditionally.',

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
            new DeliveryDecision(
                key: 'example',

                question: 'Is this delivery response defensible?',

                status: DeliveryDecision::DEFERRED,

                recommendation: null,

                rationale: 'Required truth is not established.',

                evidence: collect([
                    $this->context(),
                ]),

                constraints: collect([
                    $this->blocker(),
                ]),

                confidence: 0,

                missingTruth: collect([
                    'Canonical delivery truth is not established.',
                ]),

                asOf: $this->asOf()
            );

        $this->assertSame(
            DeliveryDecision::DEFERRED,
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
            'Deferred delivery decisions require a blocking constraint or explicit missing truth.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::DEFERRED,

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
            'Deferred delivery decisions cannot contain a recommendation.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::DEFERRED,

            recommendation: 'Proceed.',

            rationale: 'Truth is missing.',

            evidence: collect(),

            constraints: collect([
                $this->blocker(),
            ]),

            confidence: 0,

            missingTruth: collect(),

            asOf: $this->asOf()
        );
    }

    public function test_deferred_decision_confidence_is_recommendation_confidence_and_must_be_zero(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Deferred delivery decisions must have recommendation confidence 0.'
        );

        new DeliveryDecision(
            key: 'example',

            question: 'Is this deliveryly supportable?',

            status: DeliveryDecision::DEFERRED,

            recommendation: null,

            rationale: 'The blocker itself may be certain, but no recommendation is established.',

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
            DeliveryDecisionEvidence::POSITIONS
        );

        $this->assertSame(
            [
                'blocking',
                'condition',
            ],
            DeliveryDecisionConstraint::TYPES
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
                    'Delivery decision evidence confidence must be between 0 and 100.',
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
                    'Delivery decision constraint confidence must be between 0 and 100.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_contract_contains_no_priority_score_urgency_execution_or_persistence_fields(): void
    {
        foreach (
            [
                DeliveryDecision::class,
                DeliveryDecisionEvidence::class,
                DeliveryDecisionConstraint::class,
            ] as $class
        ) {
            $reflection =
                new \ReflectionClass(
                    $class
                );

            foreach (
                [
                    'priority',
                    'score',
                    'urgency',
                    'execution',
                    'executedAt',
                    'actionId',
                    'outcomeId',
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
        string $source = 'delivery_truth'
    ): DeliveryDecisionEvidence {
        return new DeliveryDecisionEvidence(
            source: $source,

            description: 'Established delivery evidence supports the guidance.',

            position: DeliveryDecisionEvidence::SUPPORTS,

            confidence: $confidence
        );
    }

    private function contradiction(
        int $confidence = 100
    ): DeliveryDecisionEvidence {
        return new DeliveryDecisionEvidence(
            source: 'delivery_truth',

            description: 'Established delivery evidence contradicts the guidance.',

            position: DeliveryDecisionEvidence::CONTRADICTS,

            confidence: $confidence
        );
    }

    private function context(
        int $confidence = 100
    ): DeliveryDecisionEvidence {
        return new DeliveryDecisionEvidence(
            source: 'delivery_context',

            description: 'Delivery context is available but does not itself support guidance.',

            position: DeliveryDecisionEvidence::CONTEXT,

            confidence: $confidence
        );
    }

    private function condition(
        int $confidence = 100
    ): DeliveryDecisionConstraint {
        return new DeliveryDecisionConstraint(
            code: 'delivery_condition',

            description: 'An explicit delivery condition remains.',

            type: DeliveryDecisionConstraint::CONDITION,

            source: 'delivery_truth',

            confidence: $confidence
        );
    }

    private function blocker(
        int $confidence = 100
    ): DeliveryDecisionConstraint {
        return new DeliveryDecisionConstraint(
            code: 'delivery_truth_blocker',

            description: 'Required delivery truth is not established.',

            type: DeliveryDecisionConstraint::BLOCKING,

            source: 'delivery_truth',

            confidence: $confidence
        );
    }

    private function asOf(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            '2026-09-04 20:45:00'
        );
    }
}
