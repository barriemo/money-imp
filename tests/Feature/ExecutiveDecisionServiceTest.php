<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGaps;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Cfo\Decision\CfoDecisionEvidence;
use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Cfo\Decision\DiscretionarySpendDecisionPolicy;
use App\Domains\Delivery\Decision\DeliveryDecision;
use App\Domains\Delivery\Decision\DeliveryDecisionEvidence;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryEvidenceReviewReadinessPolicy;
use App\Domains\Executive\Decision\ExecutiveDecision;
use App\Domains\Executive\Decision\ExecutiveDecisionContext;
use App\Domains\Executive\Decision\ExecutiveDecisionContextService;
use App\Domains\Executive\Decision\ExecutiveDecisionRequest;
use App\Domains\Executive\Decision\ExecutiveDecisionService;
use App\Domains\Executive\Decision\ManagementResponseReadinessPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ExecutiveDecisionServiceTest extends TestCase
{
    public function test_supported_request_is_contextualised_once_and_decided_by_authoritative_policy(): void
    {
        $request =
            $this->request();

        $context =
            $this->context(
                $request
            );

        $this->mock(
            ExecutiveDecisionContextService::class,
            function (
                MockInterface $mock
            ) use (
                $request,
                $context
            ): void {
                $mock
                    ->shouldReceive(
                        'forDecision'
                    )
                    ->once()
                    ->withArgs(
                        fn (
                            ExecutiveDecisionRequest $candidate
                        ): bool => $candidate === $request
                    )
                    ->andReturn(
                        $context
                    );
            }
        );

        $decision =
            app(
                ExecutiveDecisionService::class
            )->decide(
                $request
            );

        $this->assertSame(
            ExecutiveDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            80,
            $decision->confidence
        );

        $this->assertSame(
            'Proceed to human management review of this explicit cross-domain specialist decision set.',
            $decision->recommendation
        );
    }

    public function test_unsupported_request_fails_before_context_is_built(): void
    {
        $request =
            new ExecutiveDecisionRequest(
                key: 'executive_priority',
                question: 'Which management decision should be prioritised?'
            );

        $this->mock(
            ExecutiveDecisionContextService::class,
            fn (
                MockInterface $mock
            ) => $mock
                ->shouldNotReceive(
                    'forDecision'
                )
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Executive OS v1 has no authoritative policy for decision request executive_priority.'
        );

        app(
            ExecutiveDecisionService::class
        )->decide(
            $request
        );
    }

    private function request(): ExecutiveDecisionRequest
    {
        return new ExecutiveDecisionRequest(
            key: ManagementResponseReadinessPolicy::KEY,
            question: 'Does this explicit cross-domain specialist decision set support a bounded human management response now?',
            cfoRequest: new CfoDecisionRequest(
                key: DiscretionarySpendDecisionPolicy::KEY,
                question: 'Can the business safely make this discretionary spend?',
                parameters: [
                    'amount' => 5000.0,
                    'currency' => 'GBP',
                    'recurring' => false,
                ]
            ),
            deliveryRequest: new DeliveryDecisionRequest(
                key: DeliveryEvidenceReviewReadinessPolicy::KEY,
                question: 'Should the recorded delivery evidence for this exact client proceed to human delivery review now?',
                clientId: 'client-1'
            )
        );
    }

    private function context(
        ExecutiveDecisionRequest $request
    ): ExecutiveDecisionContext {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-05 11:30:00'
            );

        $cfoDecision =
            new CfoDecision(
                key: $request->cfoRequest->key,
                question: $request->cfoRequest->question,
                status: CfoDecision::RECOMMENDED,
                recommendation: 'CFO recommendation is established.',
                rationale: 'CFO support is established.',
                evidence: collect([
                    new CfoDecisionEvidence(
                        source: 'test.cfo',
                        description: 'CFO support is established.',
                        position: CfoDecisionEvidence::SUPPORTS,
                        confidence: 80
                    ),
                ]),
                constraints: collect(),
                confidence: 80,
                missingTruth: collect(),
                asOf: $asOf->addSecond()
            );

        $deliveryDecision =
            new DeliveryDecision(
                key: $request->deliveryRequest->key,
                question: $request->deliveryRequest->question,
                status: DeliveryDecision::RECOMMENDED,
                recommendation: 'Delivery recommendation is established.',
                rationale: 'Delivery support is established.',
                evidence: collect([
                    new DeliveryDecisionEvidence(
                        source: 'test.delivery',
                        description: 'Delivery support is established.',
                        position: DeliveryDecisionEvidence::SUPPORTS,
                        confidence: 90
                    ),
                ]),
                constraints: collect(),
                confidence: 90,
                missingTruth: collect(),
                asOf: $asOf->addSeconds(2)
            );

        return new ExecutiveDecisionContext(
            request: $request,
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
            commercialDecision: null,
            deliveryDecision: $deliveryDecision
        );
    }
}
