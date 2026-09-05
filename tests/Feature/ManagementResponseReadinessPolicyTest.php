<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGaps;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Cfo\Decision\CfoDecisionConstraint;
use App\Domains\Cfo\Decision\CfoDecisionEvidence;
use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Commercial\Decision\CommercialDecision;
use App\Domains\Commercial\Decision\CommercialDecisionConstraint;
use App\Domains\Commercial\Decision\CommercialDecisionEvidence;
use App\Domains\Commercial\Decision\CommercialDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryDecision;
use App\Domains\Delivery\Decision\DeliveryDecisionConstraint;
use App\Domains\Delivery\Decision\DeliveryDecisionEvidence;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Executive\Decision\ExecutiveDecision;
use App\Domains\Executive\Decision\ExecutiveDecisionConstraint;
use App\Domains\Executive\Decision\ExecutiveDecisionContext;
use App\Domains\Executive\Decision\ExecutiveDecisionEvidence;
use App\Domains\Executive\Decision\ExecutiveDecisionRequest;
use App\Domains\Executive\Decision\ManagementResponseReadinessPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class ManagementResponseReadinessPolicyTest extends TestCase
{
    public function test_policy_supports_only_management_response_readiness(): void
    {
        $policy =
            new ManagementResponseReadinessPolicy;

        $this->assertTrue(
            $policy->supports(
                new ExecutiveDecisionRequest(
                    key: ManagementResponseReadinessPolicy::KEY,

                    question: $this->question()
                )
            )
        );

        $this->assertFalse(
            $policy->supports(
                new ExecutiveDecisionRequest(
                    key: 'other_executive_decision',

                    question: $this->question()
                )
            )
        );
    }

    public function test_policy_requires_at_least_two_explicit_specialist_domains(): void
    {
        $cfoRequest =
            $this->cfoRequest();

        $context =
            $this->context(
                cfoRequest: $cfoRequest,

                cfoDecision: $this->cfoDecision(
                    request: $cfoRequest,

                    status: CfoDecision::RECOMMENDED,

                    confidence: 100,

                    asOf: $this->time(
                        '10:00:01'
                    )
                )
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        (new ManagementResponseReadinessPolicy)
            ->decide(
                $context
            );
    }

    public function test_policy_rejects_parameters_and_wrong_key(): void
    {
        $cfoRequest =
            $this->cfoRequest();

        $deliveryRequest =
            $this->deliveryRequest();

        $cfoDecision =
            $this->cfoDecision(
                request: $cfoRequest,

                status: CfoDecision::RECOMMENDED,

                confidence: 100,

                asOf: $this->time(
                    '10:00:01'
                )
            );

        $deliveryDecision =
            $this->deliveryDecision(
                request: $deliveryRequest,

                status: DeliveryDecision::RECOMMENDED,

                confidence: 100,

                asOf: $this->time(
                    '10:00:02'
                )
            );

        try {
            (new ManagementResponseReadinessPolicy)
                ->decide(
                    $this->context(
                        cfoRequest: $cfoRequest,

                        cfoDecision: $cfoDecision,

                        deliveryRequest: $deliveryRequest,

                        deliveryDecision: $deliveryDecision,

                        parameters: [
                            'mode' => 'rank',
                        ]
                    )
                );

            $this->fail(
                'Expected policy parameters to fail closed.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }

        $this->expectException(
            InvalidArgumentException::class
        );

        (new ManagementResponseReadinessPolicy)
            ->decide(
                $this->context(
                    cfoRequest: $cfoRequest,

                    cfoDecision: $cfoDecision,

                    deliveryRequest: $deliveryRequest,

                    deliveryDecision: $deliveryDecision,

                    key: 'different_executive_key'
                )
            );
    }

    public function test_established_cross_domain_decisions_recommend_human_management_review(): void
    {
        $cfoRequest =
            $this->cfoRequest();

        $deliveryRequest =
            $this->deliveryRequest();

        $cfoDecision =
            $this->cfoDecision(
                request: $cfoRequest,

                status: CfoDecision::RECOMMENDED,

                confidence: 80,

                asOf: $this->time(
                    '10:00:01'
                ),

                recommendation: 'Do not make the proposed discretionary spend.'
            );

        $deliveryDecision =
            $this->deliveryDecision(
                request: $deliveryRequest,

                status: DeliveryDecision::RECOMMENDED,

                confidence: 60,

                asOf: $this->time(
                    '10:00:02'
                ),

                recommendation: 'Proceed to human review of the recorded delivery evidence.'
            );

        $decision =
            (new ManagementResponseReadinessPolicy)
                ->decide(
                    $this->context(
                        cfoRequest: $cfoRequest,

                        cfoDecision: $cfoDecision,

                        deliveryRequest: $deliveryRequest,

                        deliveryDecision: $deliveryDecision
                    )
                );

        $this->assertSame(
            ExecutiveDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            'Proceed to human management review of this explicit cross-domain specialist decision set.',
            $decision->recommendation
        );

        $this->assertSame(
            60,
            $decision->confidence
        );

        $this->assertTrue(
            $decision->constraints->isEmpty()
        );

        $this->assertTrue(
            $decision->missingTruth->isEmpty()
        );

        $this->assertSame(
            2,
            $decision->evidence
                ->where(
                    'position',
                    ExecutiveDecisionEvidence::SUPPORTS
                )
                ->count()
        );

        $businessBrain =
            $decision->evidence
                ->firstWhere(
                    'source',
                    'business_brain.executive_context'
                );

        $this->assertNotNull(
            $businessBrain
        );

        $this->assertSame(
            ExecutiveDecisionEvidence::CONTEXT,
            $businessBrain->position
        );

        $this->assertTrue(
            $decision->asOf->equalTo(
                $this->time(
                    '10:00:02'
                )
            )
        );
    }

    public function test_specialist_recommendation_wording_does_not_change_executive_readiness_semantics(): void
    {
        $cfoRequest =
            $this->cfoRequest();

        $commercialRequest =
            $this->commercialRequest();

        $decision =
            (new ManagementResponseReadinessPolicy)
                ->decide(
                    $this->context(
                        cfoRequest: $cfoRequest,

                        cfoDecision: $this->cfoDecision(
                            request: $cfoRequest,

                            status: CfoDecision::RECOMMENDED,

                            confidence: 100,

                            asOf: $this->time(
                                '10:00:01'
                            ),

                            recommendation: 'Do not make the proposed spend.'
                        ),

                        commercialRequest: $commercialRequest,

                        commercialDecision: $this->commercialDecision(
                            request: $commercialRequest,

                            status: CommercialDecision::RECOMMENDED,

                            confidence: 100,

                            asOf: $this->time(
                                '10:00:02'
                            ),

                            recommendation: 'Do not proceed to human service reconciliation.'
                        )
                    )
                );

        $this->assertSame(
            ExecutiveDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            100,
            $decision->confidence
        );
    }

    public function test_any_deferred_specialist_defers_the_explicit_cross_domain_set(): void
    {
        $cfoRequest =
            $this->cfoRequest();

        $deliveryRequest =
            $this->deliveryRequest();

        $decision =
            (new ManagementResponseReadinessPolicy)
                ->decide(
                    $this->context(
                        cfoRequest: $cfoRequest,

                        cfoDecision: $this->cfoDecision(
                            request: $cfoRequest,

                            status: CfoDecision::RECOMMENDED,

                            confidence: 100,

                            asOf: $this->time(
                                '10:00:01'
                            )
                        ),

                        deliveryRequest: $deliveryRequest,

                        deliveryDecision: $this->deliveryDecision(
                            request: $deliveryRequest,

                            status: DeliveryDecision::DEFERRED,

                            confidence: 0,

                            asOf: $this->time(
                                '10:00:03'
                            )
                        )
                    )
                );

        $this->assertSame(
            ExecutiveDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertSame(
            0,
            $decision->confidence
        );

        $this->assertTrue(
            $decision->constraints
                ->contains(
                    fn (ExecutiveDecisionConstraint $constraint): bool => $constraint->code === 'delivery_decision_deferred'
                        && $constraint->type === ExecutiveDecisionConstraint::BLOCKING
                )
        );

        $this->assertTrue(
            $decision->missingTruth
                ->contains(
                    'DELIVERY specialist: Delivery truth remains unresolved.'
                )
        );

        $this->assertSame(
            0,
            $decision->evidence
                ->where(
                    'position',
                    ExecutiveDecisionEvidence::SUPPORTS
                )
                ->count()
        );

        $this->assertTrue(
            $decision->asOf->equalTo(
                $this->time(
                    '10:00:03'
                )
            )
        );
    }

    public function test_conditional_specialist_produces_conditional_executive_review(): void
    {
        $cfoRequest =
            $this->cfoRequest();

        $deliveryRequest =
            $this->deliveryRequest();

        $decision =
            (new ManagementResponseReadinessPolicy)
                ->decide(
                    $this->context(
                        cfoRequest: $cfoRequest,

                        cfoDecision: $this->cfoDecision(
                            request: $cfoRequest,

                            status: CfoDecision::CONDITIONAL,

                            confidence: 70,

                            asOf: $this->time(
                                '10:00:04'
                            )
                        ),

                        deliveryRequest: $deliveryRequest,

                        deliveryDecision: $this->deliveryDecision(
                            request: $deliveryRequest,

                            status: DeliveryDecision::RECOMMENDED,

                            confidence: 90,

                            asOf: $this->time(
                                '10:00:02'
                            )
                        )
                    )
                );

        $this->assertSame(
            ExecutiveDecision::CONDITIONAL,
            $decision->status
        );

        $this->assertSame(
            70,
            $decision->confidence
        );

        $this->assertSame(
            'Proceed to human management review of this explicit cross-domain specialist decision set subject to the specialist conditions already recorded.',
            $decision->recommendation
        );

        $this->assertTrue(
            $decision->constraints
                ->contains(
                    fn (ExecutiveDecisionConstraint $constraint): bool => $constraint->code === 'cfo_decision_conditional'
                        && $constraint->type === ExecutiveDecisionConstraint::CONDITION
                )
        );

        $this->assertTrue(
            $decision->missingTruth
                ->contains(
                    'CFO specialist: CFO condition remains unresolved.'
                )
        );

        $this->assertSame(
            2,
            $decision->evidence
                ->where(
                    'position',
                    ExecutiveDecisionEvidence::SUPPORTS
                )
                ->count()
        );

        $this->assertTrue(
            $decision->asOf->equalTo(
                $this->time(
                    '10:00:04'
                )
            )
        );
    }

    public function test_all_three_specialist_domains_can_be_preserved_without_aggregation(): void
    {
        $cfoRequest =
            $this->cfoRequest();

        $commercialRequest =
            $this->commercialRequest();

        $deliveryRequest =
            $this->deliveryRequest();

        $decision =
            (new ManagementResponseReadinessPolicy)
                ->decide(
                    $this->context(
                        cfoRequest: $cfoRequest,

                        cfoDecision: $this->cfoDecision(
                            request: $cfoRequest,

                            status: CfoDecision::RECOMMENDED,

                            confidence: 95,

                            asOf: $this->time(
                                '10:00:01'
                            )
                        ),

                        commercialRequest: $commercialRequest,

                        commercialDecision: $this->commercialDecision(
                            request: $commercialRequest,

                            status: CommercialDecision::RECOMMENDED,

                            confidence: 75,

                            asOf: $this->time(
                                '10:00:05'
                            )
                        ),

                        deliveryRequest: $deliveryRequest,

                        deliveryDecision: $this->deliveryDecision(
                            request: $deliveryRequest,

                            status: DeliveryDecision::RECOMMENDED,

                            confidence: 85,

                            asOf: $this->time(
                                '10:00:03'
                            )
                        )
                    )
                );

        $this->assertSame(
            ExecutiveDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            75,
            $decision->confidence
        );

        $supports =
            $decision->evidence
                ->where(
                    'position',
                    ExecutiveDecisionEvidence::SUPPORTS
                )
                ->values();

        $this->assertCount(
            3,
            $supports
        );

        $this->assertSame(
            [
                'cfo',
                'commercial',
                'delivery',
            ],
            $supports
                ->map(
                    fn (ExecutiveDecisionEvidence $evidence): string => $evidence->metadata['domain']
                )
                ->all()
        );

        $this->assertSame(
            [
                95,
                75,
                85,
            ],
            $supports
                ->map(
                    fn (ExecutiveDecisionEvidence $evidence): int => $evidence->metadata['decision_confidence']
                )
                ->all()
        );

        $this->assertTrue(
            $decision->asOf->equalTo(
                $this->time(
                    '10:00:05'
                )
            )
        );
    }

    public function test_policy_source_does_not_rank_score_execute_or_parse_specialist_recommendation_text(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/Executive/Decision/ManagementResponseReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'ExecutiveHealthReasoner',
                'ExecutiveReasoningScoreCalculator',
                'ExecutiveAttentionPolicy',
                'OpportunityEngine',
                'BusinessDecisionService',
                'ProjectActionPrioritiser',
                'ExecutiveAction',
                'RevenueRecommendationEngine',
                'CfoDecisionService',
                'CommercialDecisionService',
                'DeliveryDecisionService',
                'BusinessStateService',
                'FinancialTruthService',
                'RevenueTruthService',
                'DeliveryTruthService',
                'array_sum(',
                '->avg(',
                'average(',
                'weighted',
                'learningModifier',
                'priorityScore',
                'urgencyScore',
                'sortByDesc',
                'orderByDesc',
                '->recommendation',
                'str_contains',
                'DB::',
                '->save(',
                '->create(',
                '->update(',
                'dispatch(',
                'OpenAI',
                'Anthropic',
                'Gemini',
                'ChatGPT',
                'LLM',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function context(
        ?CfoDecisionRequest $cfoRequest = null,
        ?CfoDecision $cfoDecision = null,
        ?CommercialDecisionRequest $commercialRequest = null,
        ?CommercialDecision $commercialDecision = null,
        ?DeliveryDecisionRequest $deliveryRequest = null,
        ?DeliveryDecision $deliveryDecision = null,
        array $parameters = [],
        string $key = ManagementResponseReadinessPolicy::KEY,
    ): ExecutiveDecisionContext {
        $asOf =
            $this->time(
                '10:00:00'
            );

        return new ExecutiveDecisionContext(
            request: new ExecutiveDecisionRequest(
                key: $key,

                question: $this->question(),

                cfoRequest: $cfoRequest,

                commercialRequest: $commercialRequest,

                deliveryRequest: $deliveryRequest,

                parameters: $parameters
            ),

            state: new BusinessState(
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

    private function question(): string
    {
        return 'Does this explicit cross-domain specialist decision set support a bounded human management response now?';
    }

    private function time(
        string $time
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            '2026-09-05 '
            .$time
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

            question: 'Should the recorded delivery evidence for this exact client proceed to human delivery review now?',

            clientId: 'client-1'
        );
    }

    private function cfoDecision(
        CfoDecisionRequest $request,
        string $status,
        int $confidence,
        CarbonImmutable $asOf,
        ?string $recommendation = null,
    ): CfoDecision {
        if ($status === CfoDecision::DEFERRED) {
            return new CfoDecision(
                key: $request->key,

                question: $request->question,

                status: $status,

                recommendation: null,

                rationale: 'CFO truth remains unresolved.',

                evidence: collect([
                    new CfoDecisionEvidence(
                        source: 'test.cfo',

                        description: 'CFO decision is deferred.',

                        position: CfoDecisionEvidence::CONTEXT,

                        confidence: 100
                    ),
                ]),

                constraints: collect([
                    new CfoDecisionConstraint(
                        code: 'cfo_truth_missing',

                        description: 'CFO truth is missing.',

                        type: CfoDecisionConstraint::BLOCKING,

                        source: 'test.cfo',

                        confidence: 100
                    ),
                ]),

                confidence: 0,

                missingTruth: collect([
                    'CFO truth remains unresolved.',
                ]),

                asOf: $asOf
            );
        }

        $constraints =
            $status === CfoDecision::CONDITIONAL
                ? collect([
                    new CfoDecisionConstraint(
                        code: 'cfo_condition',

                        description: 'CFO condition remains.',

                        type: CfoDecisionConstraint::CONDITION,

                        source: 'test.cfo',

                        confidence: 100
                    ),
                ])
                : collect();

        $missingTruth =
            $status === CfoDecision::CONDITIONAL
                ? collect([
                    'CFO condition remains unresolved.',
                ])
                : collect();

        return new CfoDecision(
            key: $request->key,

            question: $request->question,

            status: $status,

            recommendation: $recommendation
                ?? 'CFO specialist recommendation is established.',

            rationale: 'CFO specialist recommendation is established under its own evidence boundary.',

            evidence: collect([
                new CfoDecisionEvidence(
                    source: 'test.cfo',

                    description: 'CFO specialist support is established.',

                    position: CfoDecisionEvidence::SUPPORTS,

                    confidence: $confidence
                ),
            ]),

            constraints: $constraints,

            confidence: $confidence,

            missingTruth: $missingTruth,

            asOf: $asOf
        );
    }

    private function commercialDecision(
        CommercialDecisionRequest $request,
        string $status,
        int $confidence,
        CarbonImmutable $asOf,
        ?string $recommendation = null,
    ): CommercialDecision {
        if ($status === CommercialDecision::DEFERRED) {
            return new CommercialDecision(
                key: $request->key,

                question: $request->question,

                status: $status,

                recommendation: null,

                rationale: 'Commercial truth remains unresolved.',

                evidence: collect([
                    new CommercialDecisionEvidence(
                        source: 'test.commercial',

                        description: 'Commercial decision is deferred.',

                        position: CommercialDecisionEvidence::CONTEXT,

                        confidence: 100
                    ),
                ]),

                constraints: collect([
                    new CommercialDecisionConstraint(
                        code: 'commercial_truth_missing',

                        description: 'Commercial truth is missing.',

                        type: CommercialDecisionConstraint::BLOCKING,

                        source: 'test.commercial',

                        confidence: 100
                    ),
                ]),

                confidence: 0,

                missingTruth: collect([
                    'Commercial truth remains unresolved.',
                ]),

                asOf: $asOf
            );
        }

        $constraints =
            $status === CommercialDecision::CONDITIONAL
                ? collect([
                    new CommercialDecisionConstraint(
                        code: 'commercial_condition',

                        description: 'Commercial condition remains.',

                        type: CommercialDecisionConstraint::CONDITION,

                        source: 'test.commercial',

                        confidence: 100
                    ),
                ])
                : collect();

        $missingTruth =
            $status === CommercialDecision::CONDITIONAL
                ? collect([
                    'Commercial condition remains unresolved.',
                ])
                : collect();

        return new CommercialDecision(
            key: $request->key,

            question: $request->question,

            status: $status,

            recommendation: $recommendation
                ?? 'Commercial specialist recommendation is established.',

            rationale: 'Commercial specialist recommendation is established under its own evidence boundary.',

            evidence: collect([
                new CommercialDecisionEvidence(
                    source: 'test.commercial',

                    description: 'Commercial specialist support is established.',

                    position: CommercialDecisionEvidence::SUPPORTS,

                    confidence: $confidence
                ),
            ]),

            constraints: $constraints,

            confidence: $confidence,

            missingTruth: $missingTruth,

            asOf: $asOf
        );
    }

    private function deliveryDecision(
        DeliveryDecisionRequest $request,
        string $status,
        int $confidence,
        CarbonImmutable $asOf,
        ?string $recommendation = null,
    ): DeliveryDecision {
        if ($status === DeliveryDecision::DEFERRED) {
            return new DeliveryDecision(
                key: $request->key,

                question: $request->question,

                status: $status,

                recommendation: null,

                rationale: 'Delivery truth remains unresolved.',

                evidence: collect([
                    new DeliveryDecisionEvidence(
                        source: 'test.delivery',

                        description: 'Delivery decision is deferred.',

                        position: DeliveryDecisionEvidence::CONTEXT,

                        confidence: 100
                    ),
                ]),

                constraints: collect([
                    new DeliveryDecisionConstraint(
                        code: 'delivery_truth_missing',

                        description: 'Delivery truth is missing.',

                        type: DeliveryDecisionConstraint::BLOCKING,

                        source: 'test.delivery',

                        confidence: 100
                    ),
                ]),

                confidence: 0,

                missingTruth: collect([
                    'Delivery truth remains unresolved.',
                ]),

                asOf: $asOf
            );
        }

        $constraints =
            $status === DeliveryDecision::CONDITIONAL
                ? collect([
                    new DeliveryDecisionConstraint(
                        code: 'delivery_condition',

                        description: 'Delivery condition remains.',

                        type: DeliveryDecisionConstraint::CONDITION,

                        source: 'test.delivery',

                        confidence: 100
                    ),
                ])
                : collect();

        $missingTruth =
            $status === DeliveryDecision::CONDITIONAL
                ? collect([
                    'Delivery condition remains unresolved.',
                ])
                : collect();

        return new DeliveryDecision(
            key: $request->key,

            question: $request->question,

            status: $status,

            recommendation: $recommendation
                ?? 'Delivery specialist recommendation is established.',

            rationale: 'Delivery specialist recommendation is established under its own evidence boundary.',

            evidence: collect([
                new DeliveryDecisionEvidence(
                    source: 'test.delivery',

                    description: 'Delivery specialist support is established.',

                    position: DeliveryDecisionEvidence::SUPPORTS,

                    confidence: $confidence
                ),
            ]),

            constraints: $constraints,

            confidence: $confidence,

            missingTruth: $missingTruth,

            asOf: $asOf
        );
    }
}
