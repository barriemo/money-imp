<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttention;
use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttentionPolicy;
use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGaps;
use App\Domains\BusinessBrain\BusinessState\BusinessStateService;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineFactory;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineSnapshotRepository;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeDetector;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidence;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidenceService;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidenceSet;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationPolicy;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use App\Domains\Cfo\Decision\CfoDecisionContext;
use App\Domains\Cfo\Decision\CfoDecisionContextService;
use App\Domains\Cfo\Decision\CfoDecisionRequest;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use ReflectionClass;
use stdClass;
use Tests\TestCase;

class CfoDecisionContextTest extends TestCase
{
    public function test_decision_request_is_inert_validated_input(): void
    {
        $request =
            new CfoDecisionRequest(
                key: 'discretionary_spend',

                question: 'Can the business safely make this discretionary spend?',

                parameters: [
                    'amount' => 5000.0,

                    'currency' => 'GBP',

                    'recurring' => false,

                    'decision_date' => null,
                ]
            );

        $this->assertSame(
            'discretionary_spend',
            $request->key
        );

        $this->assertSame(
            5000.0,
            $request->parameters['amount']
        );
    }

    public function test_decision_request_rejects_empty_identity_and_non_scalar_parameters(): void
    {
        $cases = [
            fn () => new CfoDecisionRequest(
                key: '',

                question: 'Question.'
            ),

            fn () => new CfoDecisionRequest(
                key: 'key',

                question: ''
            ),

            fn () => new CfoDecisionRequest(
                key: 'key',

                question: 'Question.',

                parameters: [
                    '' => 1,
                ]
            ),

            fn () => new CfoDecisionRequest(
                key: 'key',

                question: 'Question.',

                parameters: [
                    'object' => new stdClass,
                ]
            ),

            fn () => new CfoDecisionRequest(
                key: 'key',

                question: 'Question.',

                parameters: [
                    'nested' => [
                        'not' => 'allowed',
                    ],
                ]
            ),

            fn () => new CfoDecisionRequest(
                key: 'key',

                question: 'Question.',

                parameters: [
                    'amount' => INF,
                ]
            ),
        ];

        foreach ($cases as $case) {
            try {
                $case();

                $this->fail(
                    'Expected invalid CFO decision request to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_context_accepts_one_temporally_aligned_truth_observation(): void
    {
        $previousAsOf =
            CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            );

        $currentAsOf =
            CarbonImmutable::parse(
                '2026-09-04 16:00:00'
            );

        $state =
            $this->state(
                $currentAsOf
            );

        $previous =
            new BusinessStateBaseline(
                metrics: collect(),

                asOf: $previousAsOf
            );

        $current =
            new BusinessStateBaseline(
                metrics: collect(),

                asOf: $currentAsOf
            );

        $change =
            $this->change(
                previousAsOf: $previousAsOf,

                currentAsOf: $currentAsOf
            );

        $attention =
            new BusinessStateChangeAttention(
                change: $change,

                type: BusinessStateChangeAttention::FINANCIAL_POSITION_REDUCED,

                reason: 'Safe available cash decreased.'
            );

        $explanation =
            $this->explanation(
                $change
            );

        $context =
            new CfoDecisionContext(
                request: $this->request(),

                state: $state,

                current: $current,

                previous: $previous,

                changes: collect([
                    $change,
                ]),

                attention: collect([
                    $attention,
                ]),

                explanations: collect([
                    $explanation,
                ])
            );

        $this->assertTrue(
            $context->hasComparisonBaseline()
        );

        $this->assertTrue(
            $context->asOf()->equalTo(
                $currentAsOf
            )
        );

        $this->assertSame(
            $change,
            $context->explanations
                ->first()
                ->observation
        );
    }

    public function test_context_rejects_state_and_current_baseline_from_different_observations(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new CfoDecisionContext(
            request: $this->request(),

            state: $this->state(
                CarbonImmutable::parse(
                    '2026-09-04 16:00:01'
                )
            ),

            current: new BusinessStateBaseline(
                metrics: collect(),

                asOf: CarbonImmutable::parse(
                    '2026-09-04 16:00:00'
                )
            ),

            previous: null,

            changes: collect(),

            attention: collect(),

            explanations: collect()
        );
    }

    public function test_context_rejects_previous_baseline_that_is_not_earlier(): void
    {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-04 16:00:00'
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        new CfoDecisionContext(
            request: $this->request(),

            state: $this->state(
                $asOf
            ),

            current: new BusinessStateBaseline(
                metrics: collect(),

                asOf: $asOf
            ),

            previous: new BusinessStateBaseline(
                metrics: collect(),

                asOf: $asOf
            ),

            changes: collect(),

            attention: collect(),

            explanations: collect()
        );
    }

    public function test_context_without_previous_baseline_cannot_invent_change_attention_or_explanation(): void
    {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-04 16:00:00'
            );

        $change =
            $this->change(
                previousAsOf: $asOf->subHour(),

                currentAsOf: $asOf
            );

        foreach (
            [
                [
                    'changes' => collect([
                        $change,
                    ]),

                    'attention' => collect(),

                    'explanations' => collect(),
                ],

                [
                    'changes' => collect(),

                    'attention' => collect([
                        new BusinessStateChangeAttention(
                            change: $change,

                            type: BusinessStateChangeAttention::FINANCIAL_POSITION_REDUCED,

                            reason: 'Safe available cash decreased.'
                        ),
                    ]),

                    'explanations' => collect(),
                ],

                [
                    'changes' => collect(),

                    'attention' => collect(),

                    'explanations' => collect([
                        $this->explanation(
                            $change
                        ),
                    ]),
                ],
            ] as $case
        ) {
            try {
                new CfoDecisionContext(
                    request: $this->request(),

                    state: $this->state(
                        $asOf
                    ),

                    current: new BusinessStateBaseline(
                        metrics: collect(),

                        asOf: $asOf
                    ),

                    previous: null,

                    changes: $case['changes'],

                    attention: $case['attention'],

                    explanations: $case['explanations']
                );

                $this->fail(
                    'Expected comparison truth without a baseline to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_context_rejects_orphan_attention_and_incomplete_explanation_mapping(): void
    {
        $previousAsOf =
            CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            );

        $currentAsOf =
            CarbonImmutable::parse(
                '2026-09-04 16:00:00'
            );

        $change =
            $this->change(
                previousAsOf: $previousAsOf,

                currentAsOf: $currentAsOf
            );

        $otherChange =
            $this->change(
                previousAsOf: $previousAsOf,

                currentAsOf: $currentAsOf,

                previousValue: 20000.0,

                currentValue: 18000.0
            );

        try {
            new CfoDecisionContext(
                request: $this->request(),

                state: $this->state(
                    $currentAsOf
                ),

                current: new BusinessStateBaseline(
                    metrics: collect(),

                    asOf: $currentAsOf
                ),

                previous: new BusinessStateBaseline(
                    metrics: collect(),

                    asOf: $previousAsOf
                ),

                changes: collect([
                    $change,
                ]),

                attention: collect([
                    new BusinessStateChangeAttention(
                        change: $otherChange,

                        type: BusinessStateChangeAttention::FINANCIAL_POSITION_REDUCED,

                        reason: 'Safe available cash decreased.'
                    ),
                ]),

                explanations: collect([
                    $this->explanation(
                        $change
                    ),
                ])
            );

            $this->fail(
                'Expected orphan attention to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }

        try {
            new CfoDecisionContext(
                request: $this->request(),

                state: $this->state(
                    $currentAsOf
                ),

                current: new BusinessStateBaseline(
                    metrics: collect(),

                    asOf: $currentAsOf
                ),

                previous: new BusinessStateBaseline(
                    metrics: collect(),

                    asOf: $previousAsOf
                ),

                changes: collect([
                    $change,
                ]),

                attention: collect(),

                explanations: collect()
            );

            $this->fail(
                'Expected missing explanation mapping to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }
    }

    public function test_context_collections_are_strongly_typed(): void
    {
        $previousAsOf =
            CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            );

        $currentAsOf =
            CarbonImmutable::parse(
                '2026-09-04 16:00:00'
            );

        foreach (
            [
                [
                    'changes' => collect([
                        'not-change',
                    ]),

                    'attention' => collect(),

                    'explanations' => collect(),
                ],
                [
                    'changes' => collect(),

                    'attention' => collect([
                        'not-attention',
                    ]),

                    'explanations' => collect(),
                ],
                [
                    'changes' => collect(),

                    'attention' => collect(),

                    'explanations' => collect([
                        'not-explanation',
                    ]),
                ],
            ] as $case
        ) {
            try {
                new CfoDecisionContext(
                    request: $this->request(),

                    state: $this->state(
                        $currentAsOf
                    ),

                    current: new BusinessStateBaseline(
                        metrics: collect(),

                        asOf: $currentAsOf
                    ),

                    previous: new BusinessStateBaseline(
                        metrics: collect(),

                        asOf: $previousAsOf
                    ),

                    changes: $case['changes'],

                    attention: $case['attention'],

                    explanations: $case['explanations']
                );

                $this->fail(
                    'Expected invalid CFO decision context collection to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_first_context_has_no_comparison_and_does_not_invent_temporal_reasoning(): void
    {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-04 16:00:00'
            );

        $state =
            $this->state(
                $asOf
            );

        $current =
            new BusinessStateBaseline(
                metrics: collect(),

                asOf: $asOf
            );

        $this->mock(
            BusinessStateService::class,
            function (MockInterface $mock) use ($state): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        $state
                    );
            }
        );

        $this->mock(
            BusinessStateBaselineFactory::class,
            function (MockInterface $mock) use ($state, $current): void {
                $mock
                    ->shouldReceive('fromState')
                    ->once()
                    ->withArgs(
                        fn (BusinessState $candidate): bool => $candidate === $state
                    )
                    ->andReturn(
                        $current
                    );
            }
        );

        $this->mock(
            BusinessStateBaselineSnapshotRepository::class,
            function (MockInterface $mock) use ($asOf): void {
                $mock
                    ->shouldReceive('latestBefore')
                    ->once()
                    ->withArgs(
                        fn (CarbonImmutable $candidate): bool => $candidate->equalTo(
                            $asOf
                        )
                    )
                    ->andReturnNull();
            }
        );

        $this->mock(
            BusinessStateChangeDetector::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive(
                    'compare'
                )
        );

        $this->mock(
            BusinessStateChangeAttentionPolicy::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive(
                    'assess'
                )
        );

        $this->mock(
            BusinessStateExplanationEvidenceService::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive(
                    'forChange'
                )
        );

        $this->mock(
            BusinessStateExplanationPolicy::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive(
                    'assess'
                )
        );

        $request =
            $this->request();

        $context =
            app(
                CfoDecisionContextService::class
            )->forDecision(
                $request
            );

        $this->assertSame(
            $request,
            $context->request
        );

        $this->assertSame(
            $state,
            $context->state
        );

        $this->assertFalse(
            $context->hasComparisonBaseline()
        );

        $this->assertTrue(
            $context->changes->isEmpty()
        );

        $this->assertTrue(
            $context->attention->isEmpty()
        );

        $this->assertTrue(
            $context->explanations->isEmpty()
        );
    }

    public function test_service_uses_one_current_state_for_change_attention_and_explanation(): void
    {
        $previousAsOf =
            CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            );

        $currentAsOf =
            CarbonImmutable::parse(
                '2026-09-04 16:00:00'
            );

        $state =
            $this->state(
                $currentAsOf
            );

        $previous =
            new BusinessStateBaseline(
                metrics: collect(),

                asOf: $previousAsOf
            );

        $current =
            new BusinessStateBaseline(
                metrics: collect(),

                asOf: $currentAsOf
            );

        $change =
            $this->change(
                previousAsOf: $previousAsOf,

                currentAsOf: $currentAsOf
            );

        $changes =
            collect([
                $change,
            ]);

        $attention =
            new BusinessStateChangeAttention(
                change: $change,

                type: BusinessStateChangeAttention::FINANCIAL_POSITION_REDUCED,

                reason: 'Safe available cash decreased.'
            );

        $attentionItems =
            collect([
                $attention,
            ]);

        $evidenceSet =
            $this->explanationEvidenceSet(
                $change
            );

        $explanation =
            $this->explanation(
                $change
            );

        $this->mock(
            BusinessStateService::class,
            function (MockInterface $mock) use ($state): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        $state
                    );
            }
        );

        $this->mock(
            BusinessStateBaselineFactory::class,
            function (MockInterface $mock) use ($state, $current): void {
                $mock
                    ->shouldReceive('fromState')
                    ->once()
                    ->withArgs(
                        fn (BusinessState $candidate): bool => $candidate === $state
                    )
                    ->andReturn(
                        $current
                    );
            }
        );

        $this->mock(
            BusinessStateBaselineSnapshotRepository::class,
            function (MockInterface $mock) use ($currentAsOf, $previous): void {
                $mock
                    ->shouldReceive('latestBefore')
                    ->once()
                    ->withArgs(
                        fn (CarbonImmutable $candidate): bool => $candidate->equalTo(
                            $currentAsOf
                        )
                    )
                    ->andReturn(
                        $previous
                    );
            }
        );

        $this->mock(
            BusinessStateChangeDetector::class,
            function (MockInterface $mock) use ($previous, $current, $changes): void {
                $mock
                    ->shouldReceive('compare')
                    ->once()
                    ->withArgs(
                        fn (
                            BusinessStateBaseline $candidatePrevious,
                            BusinessStateBaseline $candidateCurrent
                        ): bool => $candidatePrevious === $previous
                            && $candidateCurrent === $current
                    )
                    ->andReturn(
                        $changes
                    );
            }
        );

        $this->mock(
            BusinessStateChangeAttentionPolicy::class,
            function (MockInterface $mock) use ($changes, $attentionItems): void {
                $mock
                    ->shouldReceive('assess')
                    ->once()
                    ->withArgs(
                        fn ($candidate): bool => $candidate === $changes
                    )
                    ->andReturn(
                        $attentionItems
                    );
            }
        );

        $this->mock(
            BusinessStateExplanationEvidenceService::class,
            function (MockInterface $mock) use ($change, $state, $evidenceSet): void {
                $mock
                    ->shouldReceive('forChange')
                    ->once()
                    ->withArgs(
                        fn (
                            BusinessStateChange $candidateChange,
                            BusinessState $candidateState
                        ): bool => $candidateChange === $change
                            && $candidateState === $state
                    )
                    ->andReturn(
                        $evidenceSet
                    );
            }
        );

        $this->mock(
            BusinessStateExplanationPolicy::class,
            function (MockInterface $mock) use ($evidenceSet, $explanation): void {
                $mock
                    ->shouldReceive('assess')
                    ->once()
                    ->withArgs(
                        fn (BusinessStateExplanationEvidenceSet $candidate): bool => $candidate === $evidenceSet
                    )
                    ->andReturn(
                        $explanation
                    );
            }
        );

        $request =
            $this->request();

        $context =
            app(
                CfoDecisionContextService::class
            )->forDecision(
                $request
            );

        $this->assertSame(
            $request,
            $context->request
        );

        $this->assertSame(
            $state,
            $context->state
        );

        $this->assertSame(
            $current,
            $context->current
        );

        $this->assertSame(
            $previous,
            $context->previous
        );

        $this->assertSame(
            $changes,
            $context->changes
        );

        $this->assertSame(
            $attentionItems,
            $context->attention
        );

        $this->assertSame(
            $explanation,
            $context->explanations->first()
        );
    }

    public function test_context_contract_contains_truth_only_and_no_recommendation_semantics(): void
    {
        $reflection =
            new ReflectionClass(
                CfoDecisionContext::class
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
                'attention',
                'changes',
                'current',
                'explanations',
                'previous',
                'request',
                'state',
            ],
            $properties
        );

        foreach (
            [
                'recommendation',
                'rationale',
                'confidence',
                'constraints',
                'status',
                'priority',
                'score',
                'urgency',
                'execution',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    private function request(): CfoDecisionRequest
    {
        return new CfoDecisionRequest(
            key: 'discretionary_spend',

            question: 'Can the business safely make this discretionary spend?',

            parameters: [
                'amount' => 5000.0,

                'currency' => 'GBP',
            ]
        );
    }

    private function state(
        CarbonImmutable $asOf
    ): BusinessState {
        return new BusinessState(
            financial: Mockery::mock(
                FinancialPosition::class
            ),

            revenue: Mockery::mock(
                RevenueTruthSummary::class
            ),

            clients: collect(),

            gaps: new BusinessStateGaps(
                unknowns: collect(),

                evidenceGaps: collect()
            ),

            asOf: $asOf
        );
    }

    private function change(
        CarbonImmutable $previousAsOf,
        CarbonImmutable $currentAsOf,
        float $previousValue = 10000.0,
        float $currentValue = 9000.0,
    ): BusinessStateChange {
        return new BusinessStateChange(
            previous: new BusinessStateMetric(
                domain: 'financial',

                metric: 'safe_available_cash',

                scope: 'business',

                clientId: null,

                client: null,

                source: 'financial.cash.safeAvailableCash',

                known: true,

                value: $previousValue
            ),

            current: new BusinessStateMetric(
                domain: 'financial',

                metric: 'safe_available_cash',

                scope: 'business',

                clientId: null,

                client: null,

                source: 'financial.cash.safeAvailableCash',

                known: true,

                value: $currentValue
            ),

            kind: BusinessStateChange::DECREASED,

            previousAsOf: $previousAsOf,

            currentAsOf: $currentAsOf
        );
    }

    private function explanationEvidenceSet(
        BusinessStateChange $change
    ): BusinessStateExplanationEvidenceSet {
        return new BusinessStateExplanationEvidenceSet(
            observation: $change,

            evidence: collect([
                $this->contextEvidence(),
            ]),

            interpretation: null,

            impact: 'Recorded safe available cash is lower than in the captured baseline.'
        );
    }

    private function explanation(
        BusinessStateChange $change
    ): BusinessStateExplanation {
        return new BusinessStateExplanation(
            observation: $change,

            status: BusinessStateExplanation::UNESTABLISHED,

            evidence: collect([
                $this->contextEvidence(),
            ]),

            interpretation: null,

            impact: 'Recorded safe available cash is lower than in the captured baseline.',

            confidence: 0,

            missingTruth: collect([
                'The record-level drivers of the change are not established.',
            ])
        );
    }

    private function contextEvidence(): BusinessStateExplanationEvidence
    {
        return new BusinessStateExplanationEvidence(
            source: 'financial.cash.safeAvailableCash',

            description: 'Safe available cash decreased.',

            position: BusinessStateExplanationEvidence::CONTEXT,

            confidence: 100
        );
    }
}
