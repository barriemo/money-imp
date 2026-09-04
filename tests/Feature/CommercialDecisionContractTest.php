<?php

namespace Tests\Feature;

use App\Domains\Commercial\Decision\CommercialDecision;
use App\Domains\Commercial\Decision\CommercialDecisionConstraint;
use App\Domains\Commercial\Decision\CommercialDecisionEvidence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class CommercialDecisionContractTest extends TestCase
{
    public function test_recommended_decision_requires_real_support_and_no_unresolved_uncertainty(): void
    {
        $decision =
            new CommercialDecision(
                key: 'example',

                question: 'Is this commercial response defensible?',

                status: CommercialDecision::RECOMMENDED,

                recommendation: 'The commercial response is defensible from established evidence.',

                rationale: 'The required commercial truth is established.',

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
            CommercialDecision::RECOMMENDED,
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
            new CommercialDecision(
                key: 'example',

                question: 'Should this commercial action proceed?',

                status: CommercialDecision::RECOMMENDED,

                recommendation: 'Do not proceed with the proposed commercial action.',

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
            CommercialDecision::RECOMMENDED,
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
            'Recommended or conditional commercial decisions require supporting evidence.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::RECOMMENDED,

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
            'Commercial decision confidence cannot exceed the weakest supporting evidence.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::RECOMMENDED,

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
            'Recommended commercial decisions cannot contain contradictory evidence.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::RECOMMENDED,

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
            'Recommended commercial decisions cannot contain unresolved constraints.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::RECOMMENDED,

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
            'Recommended commercial decisions cannot contain missing truth.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::RECOMMENDED,

            recommendation: 'Proceed.',

            rationale: 'Truth is incomplete.',

            evidence: collect([
                $this->support(),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect([
                'Canonical commercial truth is incomplete.',
            ]),

            asOf: $this->asOf()
        );
    }

    public function test_conditional_decision_requires_support_and_explicit_uncertainty(): void
    {
        $decision =
            new CommercialDecision(
                key: 'example',

                question: 'Is this commercial response defensible?',

                status: CommercialDecision::CONDITIONAL,

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
            CommercialDecision::CONDITIONAL,
            $decision->status
        );
    }

    public function test_conditional_decision_cannot_contain_blocking_constraint(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Conditional commercial decisions cannot contain blocking constraints.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::CONDITIONAL,

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
            'Conditional commercial decisions require explicit uncertainty.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::CONDITIONAL,

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
            new CommercialDecision(
                key: 'example',

                question: 'Is this commercial response defensible?',

                status: CommercialDecision::DEFERRED,

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
                    'Canonical commercial truth is not established.',
                ]),

                asOf: $this->asOf()
            );

        $this->assertSame(
            CommercialDecision::DEFERRED,
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
            'Deferred commercial decisions require a blocking constraint or explicit missing truth.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::DEFERRED,

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
            'Deferred commercial decisions cannot contain a recommendation.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::DEFERRED,

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
            'Deferred commercial decisions must have recommendation confidence 0.'
        );

        new CommercialDecision(
            key: 'example',

            question: 'Is this commercially supportable?',

            status: CommercialDecision::DEFERRED,

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
            CommercialDecisionEvidence::POSITIONS
        );

        $this->assertSame(
            [
                'blocking',
                'condition',
            ],
            CommercialDecisionConstraint::TYPES
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
                    'Commercial decision evidence confidence must be between 0 and 100.',
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
                    'Commercial decision constraint confidence must be between 0 and 100.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_contract_contains_no_priority_score_urgency_execution_or_persistence_fields(): void
    {
        foreach (
            [
                CommercialDecision::class,
                CommercialDecisionEvidence::class,
                CommercialDecisionConstraint::class,
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
        string $source = 'commercial_truth'
    ): CommercialDecisionEvidence {
        return new CommercialDecisionEvidence(
            source: $source,

            description: 'Established commercial evidence supports the guidance.',

            position: CommercialDecisionEvidence::SUPPORTS,

            confidence: $confidence
        );
    }

    private function contradiction(
        int $confidence = 100
    ): CommercialDecisionEvidence {
        return new CommercialDecisionEvidence(
            source: 'commercial_truth',

            description: 'Established commercial evidence contradicts the guidance.',

            position: CommercialDecisionEvidence::CONTRADICTS,

            confidence: $confidence
        );
    }

    private function context(
        int $confidence = 100
    ): CommercialDecisionEvidence {
        return new CommercialDecisionEvidence(
            source: 'commercial_context',

            description: 'Commercial context is available but does not itself support guidance.',

            position: CommercialDecisionEvidence::CONTEXT,

            confidence: $confidence
        );
    }

    private function condition(
        int $confidence = 100
    ): CommercialDecisionConstraint {
        return new CommercialDecisionConstraint(
            code: 'commercial_condition',

            description: 'An explicit commercial condition remains.',

            type: CommercialDecisionConstraint::CONDITION,

            source: 'commercial_truth',

            confidence: $confidence
        );
    }

    private function blocker(
        int $confidence = 100
    ): CommercialDecisionConstraint {
        return new CommercialDecisionConstraint(
            code: 'commercial_truth_blocker',

            description: 'Required commercial truth is not established.',

            type: CommercialDecisionConstraint::BLOCKING,

            source: 'commercial_truth',

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
