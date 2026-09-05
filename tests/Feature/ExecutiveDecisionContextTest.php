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
use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Cfo\Decision\CfoDecisionService;
use App\Domains\Commercial\Decision\CommercialDecision;
use App\Domains\Commercial\Decision\CommercialDecisionRequest;
use App\Domains\Commercial\Decision\CommercialDecisionService;
use App\Domains\Delivery\Decision\DeliveryDecision;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryDecisionService;
use App\Domains\Executive\Decision\ExecutiveDecisionContext;
use App\Domains\Executive\Decision\ExecutiveDecisionContextService;
use App\Domains\Executive\Decision\ExecutiveDecisionRequest;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use ReflectionClass;
use stdClass;
use Tests\TestCase;

class ExecutiveDecisionContextTest extends TestCase
{
    public function test_request_is_inert_and_carries_only_explicit_typed_specialist_requests(): void
    {
        $cfo =
            $this->cfoRequest();

        $commercial =
            $this->commercialRequest();

        $delivery =
            $this->deliveryRequest();

        $request =
            new ExecutiveDecisionRequest(
                key: 'management_attention_readiness',

                question: 'Does the established evidence support a bounded management response?',

                cfoRequest: $cfo,

                commercialRequest: $commercial,

                deliveryRequest: $delivery,

                parameters: [
                    'mode' => 'review',

                    'dry_run' => true,

                    'reference' => null,
                ]
            );

        $this->assertSame(
            'management_attention_readiness',
            $request->key
        );

        $this->assertSame(
            $cfo,
            $request->cfoRequest
        );

        $this->assertSame(
            $commercial,
            $request->commercialRequest
        );

        $this->assertSame(
            $delivery,
            $request->deliveryRequest
        );

        $this->assertTrue(
            $request->hasCfoRequest()
        );

        $this->assertTrue(
            $request->hasCommercialRequest()
        );

        $this->assertTrue(
            $request->hasDeliveryRequest()
        );
    }

    public function test_request_rejects_empty_identity_and_non_scalar_parameters(): void
    {
        $cases = [
            fn () => new ExecutiveDecisionRequest(
                key: '',

                question: 'Question.'
            ),

            fn () => new ExecutiveDecisionRequest(
                key: 'key',

                question: ''
            ),

            fn () => new ExecutiveDecisionRequest(
                key: 'key',

                question: 'Question.',

                parameters: [
                    '' => 1,
                ]
            ),

            fn () => new ExecutiveDecisionRequest(
                key: 'key',

                question: 'Question.',

                parameters: [
                    'object' => new stdClass,
                ]
            ),

            fn () => new ExecutiveDecisionRequest(
                key: 'key',

                question: 'Question.',

                parameters: [
                    'nested' => [
                        'not' => 'allowed',
                    ],
                ]
            ),

            fn () => new ExecutiveDecisionRequest(
                key: 'key',

                question: 'Question.',

                parameters: [
                    'number' => INF,
                ]
            ),
        ];

        foreach ($cases as $case) {
            try {
                $case();

                $this->fail(
                    'Expected invalid Executive decision request to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_context_accepts_one_coherent_business_brain_observation_and_preserves_specialist_decisions(): void
    {
        $previousAsOf =
            CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            );

        $currentAsOf =
            CarbonImmutable::parse(
                '2026-09-05 10:00:00'
            );

        $cfoRequest =
            $this->cfoRequest();

        $commercialRequest =
            $this->commercialRequest();

        $deliveryRequest =
            $this->deliveryRequest();

        $request =
            $this->request(
                cfo: $cfoRequest,

                commercial: $commercialRequest,

                delivery: $deliveryRequest
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

        $cfoDecision =
            $this->cfoDecision(
                request: $cfoRequest,

                asOf: $currentAsOf
                    ->addSecond()
            );

        $commercialDecision =
            $this->commercialDecision(
                request: $commercialRequest,

                asOf: CarbonImmutable::parse(
                    '2026-09-05 00:00:00'
                )
            );

        $deliveryDecision =
            $this->deliveryDecision(
                request: $deliveryRequest,

                asOf: $currentAsOf
                    ->addSeconds(2)
            );

        $context =
            new ExecutiveDecisionContext(
                request: $request,

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
                ]),

                cfoDecision: $cfoDecision,

                commercialDecision: $commercialDecision,

                deliveryDecision: $deliveryDecision
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
            $cfoDecision,
            $context->cfoDecision
        );

        $this->assertSame(
            $commercialDecision,
            $context->commercialDecision
        );

        $this->assertSame(
            $deliveryDecision,
            $context->deliveryDecision
        );

        $this->assertTrue(
            $context->cfoDecision->asOf->equalTo(
                $currentAsOf->addSecond()
            )
        );

        $this->assertTrue(
            $context->commercialDecision->asOf->equalTo(
                CarbonImmutable::parse(
                    '2026-09-05 00:00:00'
                )
            )
        );

        $this->assertTrue(
            $context->deliveryDecision->asOf->equalTo(
                $currentAsOf->addSeconds(2)
            )
        );
    }

    public function test_context_rejects_state_and_baseline_temporal_misalignment(): void
    {
        $currentAsOf =
            CarbonImmutable::parse(
                '2026-09-05 10:00:00'
            );

        try {
            new ExecutiveDecisionContext(
                request: $this->request(),

                state: $this->state(
                    $currentAsOf->addSecond()
                ),

                current: new BusinessStateBaseline(
                    metrics: collect(),

                    asOf: $currentAsOf
                ),

                previous: null,

                changes: collect(),

                attention: collect(),

                explanations: collect(),

                cfoDecision: null,

                commercialDecision: null,

                deliveryDecision: null
            );

            $this->fail(
                'Expected mismatched current observation to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }

        try {
            new ExecutiveDecisionContext(
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

                    asOf: $currentAsOf
                ),

                changes: collect(),

                attention: collect(),

                explanations: collect(),

                cfoDecision: null,

                commercialDecision: null,

                deliveryDecision: null
            );

            $this->fail(
                'Expected non-earlier previous baseline to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }
    }

    public function test_context_without_previous_baseline_cannot_invent_temporal_results(): void
    {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-05 10:00:00'
            );

        $change =
            $this->change(
                previousAsOf: $asOf->subHour(),

                currentAsOf: $asOf
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        new ExecutiveDecisionContext(
            request: $this->request(),

            state: $this->state(
                $asOf
            ),

            current: new BusinessStateBaseline(
                metrics: collect(),

                asOf: $asOf
            ),

            previous: null,

            changes: collect([
                $change,
            ]),

            attention: collect(),

            explanations: collect(),

            cfoDecision: null,

            commercialDecision: null,

            deliveryDecision: null
        );
    }

    public function test_context_rejects_orphan_attention_and_incomplete_explanation_mapping(): void
    {
        $previousAsOf =
            CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            );

        $currentAsOf =
            CarbonImmutable::parse(
                '2026-09-05 10:00:00'
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
            new ExecutiveDecisionContext(
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
                ]),

                cfoDecision: null,

                commercialDecision: null,

                deliveryDecision: null
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
            new ExecutiveDecisionContext(
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

                explanations: collect(),

                cfoDecision: null,

                commercialDecision: null,

                deliveryDecision: null
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

    public function test_context_requires_exactly_the_specialist_decisions_explicitly_requested(): void
    {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-05 10:00:00'
            );

        $cfoRequest =
            $this->cfoRequest();

        try {
            $this->contextWithoutComparison(
                request: $this->request(
                    cfo: $cfoRequest
                ),

                asOf: $asOf,

                cfoDecision: null
            );

            $this->fail(
                'Expected missing explicitly requested CFO decision to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }

        try {
            $this->contextWithoutComparison(
                request: $this->request(),

                asOf: $asOf,

                cfoDecision: $this->cfoDecision(
                    request: $cfoRequest,

                    asOf: $asOf
                )
            );

            $this->fail(
                'Expected unrequested CFO decision to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }

        $commercialRequest =
            $this->commercialRequest();

        try {
            $this->contextWithoutComparison(
                request: $this->request(
                    commercial: $commercialRequest
                ),

                asOf: $asOf,

                commercialDecision: null
            );

            $this->fail(
                'Expected missing explicitly requested Commercial decision to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }

        $deliveryRequest =
            $this->deliveryRequest();

        try {
            $this->contextWithoutComparison(
                request: $this->request(
                    delivery: $deliveryRequest
                ),

                asOf: $asOf,

                deliveryDecision: null
            );

            $this->fail(
                'Expected missing explicitly requested Delivery decision to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }
    }

    public function test_context_rejects_specialist_decision_identity_mismatch(): void
    {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-05 10:00:00'
            );

        $cfoRequest =
            $this->cfoRequest();

        $wrongKey =
            new CfoDecision(
                key: 'different_key',

                question: $cfoRequest->question,

                status: CfoDecision::DEFERRED,

                recommendation: null,

                rationale: 'Required truth is missing.',

                evidence: collect(),

                constraints: collect(),

                confidence: 0,

                missingTruth: collect([
                    'Required truth is missing.',
                ]),

                asOf: $asOf
            );

        try {
            $this->contextWithoutComparison(
                request: $this->request(
                    cfo: $cfoRequest
                ),

                asOf: $asOf,

                cfoDecision: $wrongKey
            );

            $this->fail(
                'Expected specialist decision key mismatch to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }

        $wrongQuestion =
            new CfoDecision(
                key: $cfoRequest->key,

                question: 'Different question.',

                status: CfoDecision::DEFERRED,

                recommendation: null,

                rationale: 'Required truth is missing.',

                evidence: collect(),

                constraints: collect(),

                confidence: 0,

                missingTruth: collect([
                    'Required truth is missing.',
                ]),

                asOf: $asOf
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->contextWithoutComparison(
            request: $this->request(
                cfo: $cfoRequest
            ),

            asOf: $asOf,

            cfoDecision: $wrongQuestion
        );
    }

    public function test_service_builds_one_business_brain_observation_and_calls_only_explicit_specialist_services(): void
    {
        $previousAsOf =
            CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            );

        $currentAsOf =
            CarbonImmutable::parse(
                '2026-09-05 10:00:00'
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

        $cfoRequest =
            $this->cfoRequest();

        $commercialRequest =
            $this->commercialRequest();

        $deliveryRequest =
            $this->deliveryRequest();

        $cfoDecision =
            $this->cfoDecision(
                request: $cfoRequest,

                asOf: $currentAsOf->addSecond()
            );

        $commercialDecision =
            $this->commercialDecision(
                request: $commercialRequest,

                asOf: CarbonImmutable::parse(
                    '2026-09-05 00:00:00'
                )
            );

        $deliveryDecision =
            $this->deliveryDecision(
                request: $deliveryRequest,

                asOf: $currentAsOf->addSeconds(2)
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

        $this->mock(
            CfoDecisionService::class,
            function (MockInterface $mock) use ($cfoRequest, $cfoDecision): void {
                $mock
                    ->shouldReceive('decide')
                    ->once()
                    ->withArgs(
                        fn (CfoDecisionRequest $candidate): bool => $candidate === $cfoRequest
                    )
                    ->andReturn(
                        $cfoDecision
                    );
            }
        );

        $this->mock(
            CommercialDecisionService::class,
            function (MockInterface $mock) use ($commercialRequest, $commercialDecision): void {
                $mock
                    ->shouldReceive('decide')
                    ->once()
                    ->withArgs(
                        fn (CommercialDecisionRequest $candidate): bool => $candidate === $commercialRequest
                    )
                    ->andReturn(
                        $commercialDecision
                    );
            }
        );

        $this->mock(
            DeliveryDecisionService::class,
            function (MockInterface $mock) use ($deliveryRequest, $deliveryDecision): void {
                $mock
                    ->shouldReceive('decide')
                    ->once()
                    ->withArgs(
                        fn (DeliveryDecisionRequest $candidate): bool => $candidate === $deliveryRequest
                    )
                    ->andReturn(
                        $deliveryDecision
                    );
            }
        );

        $request =
            $this->request(
                cfo: $cfoRequest,

                commercial: $commercialRequest,

                delivery: $deliveryRequest
            );

        $context =
            app(
                ExecutiveDecisionContextService::class
            )->forDecision(
                $request
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

        $this->assertSame(
            $cfoDecision,
            $context->cfoDecision
        );

        $this->assertSame(
            $commercialDecision,
            $context->commercialDecision
        );

        $this->assertSame(
            $deliveryDecision,
            $context->deliveryDecision
        );
    }

    public function test_service_without_previous_baseline_or_specialist_requests_does_not_invent_or_select(): void
    {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-05 10:00:00'
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

        $this->mock(
            CfoDecisionService::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        $this->mock(
            CommercialDecisionService::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        $this->mock(
            DeliveryDecisionService::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        $context =
            app(
                ExecutiveDecisionContextService::class
            )->forDecision(
                $this->request()
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

        $this->assertNull(
            $context->cfoDecision
        );

        $this->assertNull(
            $context->commercialDecision
        );

        $this->assertNull(
            $context->deliveryDecision
        );
    }

    public function test_context_contract_preserves_typed_specialists_without_aggregate_decision_semantics(): void
    {
        $reflection =
            new ReflectionClass(
                ExecutiveDecisionContext::class
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
                'cfoDecision',
                'changes',
                'commercialDecision',
                'current',
                'deliveryDecision',
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
                'ranking',
                'rank',
                'health',
                'overallConfidence',
                'execution',
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

        $requestReflection =
            new ReflectionClass(
                ExecutiveDecisionRequest::class
            );

        foreach (
            [
                'clientId',
                'candidateFingerprint',
                'evidenceFingerprint',
                'priority',
                'score',
                'urgency',
                'ranking',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $requestReflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    public function test_context_service_uses_public_specialist_decision_services_only(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/Executive/Decision/ExecutiveDecisionContextService.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                CfoDecisionService::class,
                CommercialDecisionService::class,
                DeliveryDecisionService::class,
            ] as $required
        ) {
            $this->assertStringContainsString(
                class_basename(
                    $required
                ),
                $source
            );
        }

        foreach (
            [
                'CfoDecisionContextService',
                'CommercialDecisionContextService',
                'DeliveryDecisionContextService',
                'DiscretionarySpendDecisionPolicy',
                'ServiceReconciliationReadinessPolicy',
                'DeliveryEvidenceReviewReadinessPolicy',
                'CfoDecisionPresenter',
                'CommercialDecisionPresenter',
                'DeliveryDecisionPresenter',
                'BusinessStateChangeReportService',
                'BusinessStateExplanationReportService',
                'ExecutiveHealthReasoner',
                'ExecutiveReasoningScoreCalculator',
                'ExecutiveAttentionPolicy',
                'OpportunityEngine',
                'BusinessDecisionService',
                'ExecutiveAction',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function request(
        ?CfoDecisionRequest $cfo = null,
        ?CommercialDecisionRequest $commercial = null,
        ?DeliveryDecisionRequest $delivery = null,
    ): ExecutiveDecisionRequest {
        return new ExecutiveDecisionRequest(
            key: 'management_attention_readiness',

            question: 'Does the established evidence support a bounded management response?',

            cfoRequest: $cfo,

            commercialRequest: $commercial,

            deliveryRequest: $delivery
        );
    }

    private function cfoRequest(): CfoDecisionRequest
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

    private function commercialRequest(): CommercialDecisionRequest
    {
        return new CommercialDecisionRequest(
            key: 'service_reconciliation_readiness',

            question: 'Should this exact commercial evidence set proceed to human service reconciliation now?',

            clientId: 'client-1',

            candidateFingerprint: 'candidate-1',

            evidenceFingerprint: 'evidence-1'
        );
    }

    private function deliveryRequest(): DeliveryDecisionRequest
    {
        return new DeliveryDecisionRequest(
            key: 'delivery_evidence_review',

            question: 'Should this client recorded delivery evidence proceed to human delivery review now?',

            clientId: 'client-1'
        );
    }

    private function contextWithoutComparison(
        ExecutiveDecisionRequest $request,
        CarbonImmutable $asOf,
        ?CfoDecision $cfoDecision = null,
        ?CommercialDecision $commercialDecision = null,
        ?DeliveryDecision $deliveryDecision = null,
    ): ExecutiveDecisionContext {
        return new ExecutiveDecisionContext(
            request: $request,

            state: $this->state(
                $asOf
            ),

            current: new BusinessStateBaseline(
                metrics: collect(),

                asOf: $asOf
            ),

            previous: null,

            changes: collect(),

            attention: collect(),

            explanations: collect(),

            cfoDecision: $cfoDecision,

            commercialDecision: $commercialDecision,

            deliveryDecision: $deliveryDecision
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

    private function cfoDecision(
        CfoDecisionRequest $request,
        CarbonImmutable $asOf
    ): CfoDecision {
        return new CfoDecision(
            key: $request->key,

            question: $request->question,

            status: CfoDecision::DEFERRED,

            recommendation: null,

            rationale: 'Required financial truth is not established.',

            evidence: collect(),

            constraints: collect(),

            confidence: 0,

            missingTruth: collect([
                'Required financial truth is missing.',
            ]),

            asOf: $asOf
        );
    }

    private function commercialDecision(
        CommercialDecisionRequest $request,
        CarbonImmutable $asOf
    ): CommercialDecision {
        return new CommercialDecision(
            key: $request->key,

            question: $request->question,

            status: CommercialDecision::DEFERRED,

            recommendation: null,

            rationale: 'Required commercial truth is not established.',

            evidence: collect(),

            constraints: collect(),

            confidence: 0,

            missingTruth: collect([
                'Required commercial truth is missing.',
            ]),

            asOf: $asOf
        );
    }

    private function deliveryDecision(
        DeliveryDecisionRequest $request,
        CarbonImmutable $asOf
    ): DeliveryDecision {
        return new DeliveryDecision(
            key: $request->key,

            question: $request->question,

            status: DeliveryDecision::DEFERRED,

            recommendation: null,

            rationale: 'Required delivery truth is not established.',

            evidence: collect(),

            constraints: collect(),

            confidence: 0,

            missingTruth: collect([
                'Required delivery truth is missing.',
            ]),

            asOf: $asOf
        );
    }
}
