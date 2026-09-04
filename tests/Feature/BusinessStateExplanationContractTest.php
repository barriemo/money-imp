<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class BusinessStateExplanationContractTest extends TestCase
{
    public function test_unestablished_explanation_can_prove_change_without_inventing_why(): void
    {
        $observation =
            $this->change();

        $explanation =
            new BusinessStateExplanation(
                observation: $observation,

                status: BusinessStateExplanation::UNESTABLISHED,

                evidence: collect([
                    $this->contextEvidence(
                        source: 'revenue.outstanding',

                        description: 'Outstanding invoiced revenue increased from £1,000.00 to £1,500.00.'
                    ),
                ]),

                interpretation: null,

                impact: 'More invoiced revenue is represented as outstanding.',

                confidence: 0,

                missingTruth: collect([
                    'Invoice-age movement is not established.',
                    'The contribution from newly issued invoices is not established.',
                    'Payment-timing movement is not established.',
                ])
            );

        $this->assertSame(
            $observation,
            $explanation->observation
        );

        $this->assertSame(
            BusinessStateExplanation::UNESTABLISHED,
            $explanation->status
        );

        $this->assertNull(
            $explanation->interpretation
        );

        $this->assertSame(
            0,
            $explanation->confidence
        );

        $this->assertCount(
            3,
            $explanation->missingTruth
        );

        $this->assertFalse(
            property_exists(
                $explanation,
                'priority'
            )
        );

        $this->assertFalse(
            property_exists(
                $explanation,
                'score'
            )
        );

        $this->assertFalse(
            property_exists(
                $explanation,
                'recommendation'
            )
        );

        $this->assertFalse(
            property_exists(
                $explanation,
                'action'
            )
        );
    }

    public function test_established_explanation_requires_clean_support_and_complete_truth(): void
    {
        $support =
            new BusinessStateExplanationEvidence(
                source: 'invoice.ageing',

                description: 'Invoice-age evidence establishes that £500 moved into older outstanding age bands.',

                position: BusinessStateExplanationEvidence::SUPPORTS,

                confidence: 95,

                metadata: [
                    'movement' => 500,
                ]
            );

        $explanation =
            new BusinessStateExplanation(
                observation: $this->change(),

                status: BusinessStateExplanation::ESTABLISHED,

                evidence: collect([
                    $support,
                ]),

                interpretation: 'The increase is attributable to established invoice-age movement.',

                impact: 'A larger amount of invoiced revenue is remaining outstanding for longer.',

                confidence: 95,

                missingTruth: collect()
            );

        $this->assertSame(
            BusinessStateExplanation::ESTABLISHED,
            $explanation->status
        );

        $this->assertSame(
            95,
            $explanation->confidence
        );

        $this->assertSame(
            $support,
            $explanation->evidence
                ->first()
        );

        $this->assertSame(
            500,
            $explanation->evidence
                ->first()
                ->metadata['movement']
        );
    }

    public function test_partial_explanation_can_preserve_support_and_missing_truth(): void
    {
        $explanation =
            new BusinessStateExplanation(
                observation: $this->change(),

                status: BusinessStateExplanation::PARTIAL,

                evidence: collect([
                    $this->supportEvidence(),
                ]),

                interpretation: 'Older unpaid invoices account for part of the increase.',

                impact: 'Some of the increased outstanding balance is represented by older invoices.',

                confidence: 70,

                missingTruth: collect([
                    'The remainder of the increase is not yet attributed.',
                ])
            );

        $this->assertSame(
            BusinessStateExplanation::PARTIAL,
            $explanation->status
        );

        $this->assertSame(
            70,
            $explanation->confidence
        );

        $this->assertCount(
            1,
            $explanation->missingTruth
        );
    }

    public function test_partial_explanation_can_preserve_conflicting_evidence(): void
    {
        $explanation =
            new BusinessStateExplanation(
                observation: $this->change(),

                status: BusinessStateExplanation::PARTIAL,

                evidence: collect([
                    $this->supportEvidence(),

                    new BusinessStateExplanationEvidence(
                        source: 'payment.timing',

                        description: 'Payment timing evidence does not show a corresponding slowdown.',

                        position: BusinessStateExplanationEvidence::CONTRADICTS,

                        confidence: 80
                    ),
                ]),

                interpretation: 'Older invoices may explain part of the increase.',

                impact: 'The observed increase cannot yet be attributed to one established driver.',

                confidence: 60,

                missingTruth: collect()
            );

        $this->assertSame(
            BusinessStateExplanation::PARTIAL,
            $explanation->status
        );

        $this->assertCount(
            2,
            $explanation->evidence
        );
    }

    public function test_unestablished_explanation_rejects_interpretation_confidence_or_positioned_evidence(): void
    {
        foreach (
            [
                [
                    'interpretation' => 'A cause was asserted.',

                    'confidence' => 0,

                    'evidence' => collect(),
                ],

                [
                    'interpretation' => null,

                    'confidence' => 20,

                    'evidence' => collect(),
                ],

                [
                    'interpretation' => null,

                    'confidence' => 0,

                    'evidence' => collect([
                        $this->supportEvidence(),
                    ]),
                ],
            ] as $case
        ) {
            try {
                new BusinessStateExplanation(
                    observation: $this->change(),

                    status: BusinessStateExplanation::UNESTABLISHED,

                    evidence: $case['evidence'],

                    interpretation: $case['interpretation'],

                    impact: 'The observed balance is higher.',

                    confidence: $case['confidence'],

                    missingTruth: collect([
                        'The causal driver is not established.',
                    ])
                );

                $this->fail(
                    'Invalid unestablished explanation was accepted.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_established_explanation_rejects_missing_support_contradiction_or_missing_truth(): void
    {
        $cases = [
            [
                'evidence' => collect(),

                'missing' => collect(),
            ],

            [
                'evidence' => collect([
                    $this->supportEvidence(),

                    new BusinessStateExplanationEvidence(
                        source: 'other.source',

                        description: 'Other evidence contradicts the interpretation.',

                        position: BusinessStateExplanationEvidence::CONTRADICTS,

                        confidence: 90
                    ),
                ]),

                'missing' => collect(),
            ],

            [
                'evidence' => collect([
                    $this->supportEvidence(),
                ]),

                'missing' => collect([
                    'Material attribution remains unresolved.',
                ]),
            ],
        ];

        foreach ($cases as $case) {
            try {
                new BusinessStateExplanation(
                    observation: $this->change(),

                    status: BusinessStateExplanation::ESTABLISHED,

                    evidence: $case['evidence'],

                    interpretation: 'The increase has one established driver.',

                    impact: 'Outstanding revenue increased.',

                    confidence: 90,

                    missingTruth: $case['missing']
                );

                $this->fail(
                    'Invalid established explanation was accepted.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_partial_explanation_requires_explicit_uncertainty(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new BusinessStateExplanation(
            observation: $this->change(),

            status: BusinessStateExplanation::PARTIAL,

            evidence: collect([
                $this->supportEvidence(),
            ]),

            interpretation: 'The increase has an asserted driver.',

            impact: 'Outstanding revenue increased.',

            confidence: 80,

            missingTruth: collect()
        );
    }

    public function test_evidence_and_explanation_invariants_fail_closed(): void
    {
        foreach (
            [
                fn () => new BusinessStateExplanationEvidence(
                    source: '',

                    description: 'Evidence.',

                    position: BusinessStateExplanationEvidence::CONTEXT,

                    confidence: 100
                ),

                fn () => new BusinessStateExplanationEvidence(
                    source: 'test',

                    description: '',

                    position: BusinessStateExplanationEvidence::CONTEXT,

                    confidence: 100
                ),

                fn () => new BusinessStateExplanationEvidence(
                    source: 'test',

                    description: 'Evidence.',

                    position: 'missing',

                    confidence: 100
                ),

                fn () => new BusinessStateExplanationEvidence(
                    source: 'test',

                    description: 'Evidence.',

                    position: BusinessStateExplanationEvidence::CONTEXT,

                    confidence: 101
                ),

                fn () => new BusinessStateExplanation(
                    observation: $this->change(),

                    status: 'certain',

                    evidence: collect(),

                    interpretation: null,

                    impact: 'Impact.',

                    confidence: 0,

                    missingTruth: collect([
                        'Missing truth.',
                    ])
                ),

                fn () => new BusinessStateExplanation(
                    observation: $this->change(),

                    status: BusinessStateExplanation::UNESTABLISHED,

                    evidence: collect([
                        'not evidence',
                    ]),

                    interpretation: null,

                    impact: 'Impact.',

                    confidence: 0,

                    missingTruth: collect([
                        'Missing truth.',
                    ])
                ),

                fn () => new BusinessStateExplanation(
                    observation: $this->change(),

                    status: BusinessStateExplanation::UNESTABLISHED,

                    evidence: collect(),

                    interpretation: null,

                    impact: '',

                    confidence: 0,

                    missingTruth: collect([
                        'Missing truth.',
                    ])
                ),

                fn () => new BusinessStateExplanation(
                    observation: $this->change(),

                    status: BusinessStateExplanation::UNESTABLISHED,

                    evidence: collect(),

                    interpretation: null,

                    impact: 'Impact.',

                    confidence: 0,

                    missingTruth: collect([
                        '',
                    ])
                ),
            ] as $invalid
        ) {
            try {
                $invalid();

                $this->fail(
                    'Invalid explanation contract input was accepted.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    private function supportEvidence(): BusinessStateExplanationEvidence
    {
        return new BusinessStateExplanationEvidence(
            source: 'invoice.ageing',

            description: 'Older invoice balances increased.',

            position: BusinessStateExplanationEvidence::SUPPORTS,

            confidence: 90
        );
    }

    private function contextEvidence(
        string $source,
        string $description,
    ): BusinessStateExplanationEvidence {
        return new BusinessStateExplanationEvidence(
            source: $source,

            description: $description,

            position: BusinessStateExplanationEvidence::CONTEXT,

            confidence: 100
        );
    }

    private function change(): BusinessStateChange
    {
        return new BusinessStateChange(
            previous: $this->metric(
                1000
            ),

            current: $this->metric(
                1500
            ),

            kind: BusinessStateChange::INCREASED,

            previousAsOf: CarbonImmutable::parse(
                '2026-09-04 12:00:00'
            ),

            currentAsOf: CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            )
        );
    }

    private function metric(
        int|float $value
    ): BusinessStateMetric {
        return new BusinessStateMetric(
            domain: 'commercial',

            metric: 'outstanding_invoiced_revenue',

            scope: 'business',

            clientId: null,

            client: null,

            source: 'revenue.outstanding',

            known: true,

            value: $value
        );
    }
}
