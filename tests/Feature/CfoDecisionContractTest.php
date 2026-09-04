<?php

namespace Tests\Feature;

use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Cfo\Decision\CfoDecisionConstraint;
use App\Domains\Cfo\Decision\CfoDecisionEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

class CfoDecisionContractTest extends TestCase
{
    public function test_decision_evidence_has_only_support_contradiction_and_context_positions(): void
    {
        $this->assertSame(
            [
                'supports',
                'contradicts',
                'context',
            ],
            CfoDecisionEvidence::POSITIONS
        );

        $evidence =
            new CfoDecisionEvidence(
                source: 'business_state.financial.cash',

                description: 'Safe available cash is established at £25,000.',

                position: CfoDecisionEvidence::SUPPORTS,

                confidence: 100,

                metadata: [
                    'metric' => 'safe_available_cash',
                ]
            );

        $this->assertSame(
            CfoDecisionEvidence::SUPPORTS,
            $evidence->position
        );

        $this->assertSame(
            100,
            $evidence->confidence
        );
    }

    public function test_decision_evidence_rejects_invalid_contract_values(): void
    {
        foreach (
            [
                [
                    '',
                    'Evidence.',
                    CfoDecisionEvidence::SUPPORTS,
                    100,
                ],
                [
                    'source',
                    '',
                    CfoDecisionEvidence::SUPPORTS,
                    100,
                ],
                [
                    'source',
                    'Evidence.',
                    'missing',
                    100,
                ],
                [
                    'source',
                    'Evidence.',
                    CfoDecisionEvidence::SUPPORTS,
                    -1,
                ],
                [
                    'source',
                    'Evidence.',
                    CfoDecisionEvidence::SUPPORTS,
                    101,
                ],
            ] as $values
        ) {
            try {
                new CfoDecisionEvidence(
                    source: $values[0],

                    description: $values[1],

                    position: $values[2],

                    confidence: $values[3]
                );

                $this->fail(
                    'Expected invalid CFO decision evidence to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_constraints_distinguish_blocking_truth_from_conditions(): void
    {
        $this->assertSame(
            [
                'blocking',
                'condition',
            ],
            CfoDecisionConstraint::TYPES
        );

        $constraint =
            new CfoDecisionConstraint(
                code: 'safe_cash_unknown',

                description: 'Safe available cash is not established.',

                type: CfoDecisionConstraint::BLOCKING,

                source: 'business_state.gap.safe_available_cash_unknown',

                confidence: 100
            );

        $this->assertSame(
            CfoDecisionConstraint::BLOCKING,
            $constraint->type
        );
    }

    public function test_constraint_rejects_invalid_values(): void
    {
        foreach (
            [
                [
                    '',
                    'Constraint.',
                    CfoDecisionConstraint::BLOCKING,
                    'source',
                    100,
                ],
                [
                    'code',
                    '',
                    CfoDecisionConstraint::BLOCKING,
                    'source',
                    100,
                ],
                [
                    'code',
                    'Constraint.',
                    'priority',
                    'source',
                    100,
                ],
                [
                    'code',
                    'Constraint.',
                    CfoDecisionConstraint::BLOCKING,
                    '',
                    100,
                ],
                [
                    'code',
                    'Constraint.',
                    CfoDecisionConstraint::BLOCKING,
                    'source',
                    101,
                ],
            ] as $values
        ) {
            try {
                new CfoDecisionConstraint(
                    code: $values[0],

                    description: $values[1],

                    type: $values[2],

                    source: $values[3],

                    confidence: $values[4]
                );

                $this->fail(
                    'Expected invalid CFO decision constraint to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_recommended_decision_requires_supported_unconstrained_truth(): void
    {
        $decision =
            new CfoDecision(
                key: 'discretionary_spend',

                question: 'Can the business safely make this discretionary spend?',

                status: CfoDecision::RECOMMENDED,

                recommendation: 'The spend is supportable from established safe available cash.',

                rationale: 'Established safe available cash exceeds the proposed spend while current liabilities are fully covered.',

                evidence: collect([
                    $this->support(
                        confidence: 95
                    ),
                ]),

                constraints: collect(),

                confidence: 95,

                missingTruth: collect(),

                asOf: $this->asOf()
            );

        $this->assertSame(
            CfoDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            95,
            $decision->confidence
        );
    }

    public function test_recommended_decision_rejects_contradiction_constraint_or_missing_truth(): void
    {
        $cases = [
            [
                'evidence' => collect([
                    $this->support(),
                    $this->contradiction(),
                ]),

                'constraints' => collect(),

                'missing' => collect(),
            ],
            [
                'evidence' => collect([
                    $this->support(),
                ]),

                'constraints' => collect([
                    $this->condition(),
                ]),

                'missing' => collect(),
            ],
            [
                'evidence' => collect([
                    $this->support(),
                ]),

                'constraints' => collect(),

                'missing' => collect([
                    'Future committed cash requirements are not established.',
                ]),
            ],
        ];

        foreach ($cases as $case) {
            try {
                $this->decision(
                    status: CfoDecision::RECOMMENDED,

                    recommendation: 'Proceed.',

                    evidence: $case['evidence'],

                    constraints: $case['constraints'],

                    confidence: 90,

                    missingTruth: $case['missing']
                );

                $this->fail(
                    'Expected unsupported recommended decision to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_conditional_decision_requires_support_and_explicit_uncertainty(): void
    {
        $decision =
            $this->decision(
                status: CfoDecision::CONDITIONAL,

                recommendation: 'The spend may be supportable if the outstanding liability condition is resolved.',

                evidence: collect([
                    $this->support(
                        confidence: 90
                    ),
                ]),

                constraints: collect([
                    $this->condition(),
                ]),

                confidence: 90,

                missingTruth: collect()
            );

        $this->assertSame(
            CfoDecision::CONDITIONAL,
            $decision->status
        );

        $this->assertCount(
            1,
            $decision->constraints
        );
    }

    public function test_conditional_decision_may_express_contradiction_or_missing_truth(): void
    {
        $withContradiction =
            $this->decision(
                status: CfoDecision::CONDITIONAL,

                recommendation: 'Proceed only within the established cash boundary.',

                evidence: collect([
                    $this->support(),
                    $this->contradiction(),
                ]),

                constraints: collect(),

                confidence: 80,

                missingTruth: collect()
            );

        $this->assertSame(
            CfoDecision::CONDITIONAL,
            $withContradiction->status
        );

        $withMissingTruth =
            $this->decision(
                status: CfoDecision::CONDITIONAL,

                recommendation: 'Proceed only if the missing committed-cash position is established.',

                evidence: collect([
                    $this->support(),
                ]),

                constraints: collect(),

                confidence: 75,

                missingTruth: collect([
                    'Committed cash requirements for the decision period are not established.',
                ])
            );

        $this->assertSame(
            CfoDecision::CONDITIONAL,
            $withMissingTruth->status
        );
    }

    public function test_conditional_decision_rejects_blocking_constraint(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->decision(
            status: CfoDecision::CONDITIONAL,

            recommendation: 'Proceed if the blocking truth is later established.',

            evidence: collect([
                $this->support(),
            ]),

            constraints: collect([
                $this->blocking(),
            ]),

            confidence: 90,

            missingTruth: collect()
        );
    }

    public function test_decision_confidence_cannot_exceed_weakest_supporting_evidence(): void
    {
        $allowed =
            $this->decision(
                status: CfoDecision::RECOMMENDED,

                recommendation: 'Proceed within the established financial boundary.',

                evidence: collect([
                    $this->support(
                        confidence: 95
                    ),

                    $this->support(
                        confidence: 70
                    ),
                ]),

                constraints: collect(),

                confidence: 70,

                missingTruth: collect()
            );

        $this->assertSame(
            70,
            $allowed->confidence
        );

        try {
            $this->decision(
                status: CfoDecision::RECOMMENDED,

                recommendation: 'Proceed.',

                evidence: collect([
                    $this->support(
                        confidence: 95
                    ),

                    $this->support(
                        confidence: 70
                    ),
                ]),

                constraints: collect(),

                confidence: 71,

                missingTruth: collect()
            );

            $this->fail(
                'Expected recommendation confidence above weakest support to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }
    }

    public function test_conditional_decision_cannot_be_uncertainty_free(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->decision(
            status: CfoDecision::CONDITIONAL,

            recommendation: 'Proceed.',

            evidence: collect([
                $this->support(),
            ]),

            constraints: collect(),

            confidence: 90,

            missingTruth: collect()
        );
    }

    public function test_deferred_decision_has_no_recommendation_and_zero_recommendation_confidence(): void
    {
        $decision =
            $this->decision(
                status: CfoDecision::DEFERRED,

                recommendation: null,

                evidence: collect([
                    $this->context(),
                ]),

                constraints: collect([
                    new CfoDecisionConstraint(
                        code: 'safe_available_cash_unknown',

                        description: 'Safe available cash is not established.',

                        type: CfoDecisionConstraint::BLOCKING,

                        source: 'business_state.gap.safe_available_cash_unknown',

                        confidence: 100
                    ),
                ]),

                confidence: 0,

                missingTruth: collect([
                    'Complete current bank and liability evidence is not available.',
                ])
            );

        $this->assertSame(
            CfoDecision::DEFERRED,
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

    public function test_deferred_decision_requires_a_reason_it_cannot_recommend(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->decision(
            status: CfoDecision::DEFERRED,

            recommendation: null,

            evidence: collect([
                $this->context(),
            ]),

            constraints: collect(),

            confidence: 0,

            missingTruth: collect()
        );
    }

    public function test_deferred_decision_rejects_recommendation_or_positive_recommendation_confidence(): void
    {
        foreach (
            [
                [
                    'recommendation' => 'Proceed.',

                    'confidence' => 0,
                ],
                [
                    'recommendation' => null,

                    'confidence' => 50,
                ],
            ] as $case
        ) {
            try {
                $this->decision(
                    status: CfoDecision::DEFERRED,

                    recommendation: $case['recommendation'],

                    evidence: collect([
                        $this->context(),
                    ]),

                    constraints: collect([
                        $this->blocking(),
                    ]),

                    confidence: $case['confidence'],

                    missingTruth: collect()
                );

                $this->fail(
                    'Expected invalid deferred decision to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_non_deferred_decision_requires_support_recommendation_and_positive_confidence(): void
    {
        $cases = [
            [
                'recommendation' => null,

                'evidence' => collect([
                    $this->support(),
                ]),

                'confidence' => 90,
            ],
            [
                'recommendation' => 'Proceed.',

                'evidence' => collect([
                    $this->context(),
                ]),

                'confidence' => 90,
            ],
            [
                'recommendation' => 'Proceed.',

                'evidence' => collect([
                    $this->support(),
                ]),

                'confidence' => 0,
            ],
        ];

        foreach ($cases as $case) {
            try {
                $this->decision(
                    status: CfoDecision::RECOMMENDED,

                    recommendation: $case['recommendation'],

                    evidence: $case['evidence'],

                    constraints: collect(),

                    confidence: $case['confidence'],

                    missingTruth: collect()
                );

                $this->fail(
                    'Expected invalid CFO decision to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_decision_collections_are_strongly_typed_and_missing_truth_is_explicit(): void
    {
        foreach (
            [
                [
                    'evidence' => collect([
                        'not-evidence',
                    ]),

                    'constraints' => collect(),

                    'missing' => collect([
                        'Missing truth.',
                    ]),
                ],
                [
                    'evidence' => collect([
                        $this->context(),
                    ]),

                    'constraints' => collect([
                        'not-constraint',
                    ]),

                    'missing' => collect([
                        'Missing truth.',
                    ]),
                ],
                [
                    'evidence' => collect([
                        $this->context(),
                    ]),

                    'constraints' => collect(),

                    'missing' => collect([
                        '',
                    ]),
                ],
            ] as $case
        ) {
            try {
                $this->decision(
                    status: CfoDecision::DEFERRED,

                    recommendation: null,

                    evidence: $case['evidence'],

                    constraints: $case['constraints'],

                    confidence: 0,

                    missingTruth: $case['missing']
                );

                $this->fail(
                    'Expected invalid decision collection to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_public_decision_contract_contains_guidance_but_no_execution_or_priority_fields(): void
    {
        $reflection =
            new ReflectionClass(
                CfoDecision::class
            );

        $properties =
            collect(
                $reflection->getProperties()
            )
                ->filter(
                    fn ($property): bool => $property->isPublic()
                )
                ->map(
                    fn ($property): string => $property->getName()
                )
                ->sort()
                ->values()
                ->all();

        $this->assertSame(
            [
                'asOf',
                'confidence',
                'constraints',
                'evidence',
                'key',
                'missingTruth',
                'question',
                'rationale',
                'recommendation',
                'status',
            ],
            $properties
        );

        foreach (
            [
                'priority',
                'score',
                'urgency',
                'execution',
                'executedAt',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    private function decision(
        string $status,
        ?string $recommendation,
        Collection $evidence,
        Collection $constraints,
        int $confidence,
        Collection $missingTruth,
    ): CfoDecision {
        return new CfoDecision(
            key: 'discretionary_spend',

            question: 'Can the business safely make this discretionary spend?',

            status: $status,

            recommendation: $recommendation,

            rationale: 'Decision guidance is bounded by established current financial truth.',

            evidence: $evidence,

            constraints: $constraints,

            confidence: $confidence,

            missingTruth: $missingTruth,

            asOf: $this->asOf()
        );
    }

    private function support(
        int $confidence = 90
    ): CfoDecisionEvidence {
        return new CfoDecisionEvidence(
            source: 'business_state.financial.safe_available_cash',

            description: 'Safe available cash is established.',

            position: CfoDecisionEvidence::SUPPORTS,

            confidence: $confidence
        );
    }

    private function contradiction(): CfoDecisionEvidence
    {
        return new CfoDecisionEvidence(
            source: 'business_state.financial.liability_exposure',

            description: 'A current financial obligation conflicts with the proposed spend boundary.',

            position: CfoDecisionEvidence::CONTRADICTS,

            confidence: 80
        );
    }

    private function context(): CfoDecisionEvidence
    {
        return new CfoDecisionEvidence(
            source: 'business_state.financial',

            description: 'Current financial truth is available but incomplete for this decision.',

            position: CfoDecisionEvidence::CONTEXT,

            confidence: 100
        );
    }

    private function condition(): CfoDecisionConstraint
    {
        return new CfoDecisionConstraint(
            code: 'liability_condition',

            description: 'The decision remains subject to unresolved liability evidence.',

            type: CfoDecisionConstraint::CONDITION,

            source: 'business_state.financial.liabilities',

            confidence: 100
        );
    }

    private function blocking(): CfoDecisionConstraint
    {
        return new CfoDecisionConstraint(
            code: 'safe_cash_unknown',

            description: 'Safe available cash is not established.',

            type: CfoDecisionConstraint::BLOCKING,

            source: 'business_state.gap.safe_available_cash_unknown',

            confidence: 100
        );
    }

    private function asOf(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            '2026-09-04 16:00:00'
        );
    }
}
