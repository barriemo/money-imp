<?php

namespace Tests\Feature;

use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Cfo\Decision\DiscretionarySpendDecisionPolicy;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryEvidenceReviewReadinessPolicy;
use App\Domains\Executive\Decision\ExecutiveDecision;
use App\Domains\Executive\Decision\ExecutiveDecisionEvidence;
use App\Domains\Executive\Decision\ExecutiveDecisionRequest;
use App\Domains\Executive\Decision\ExecutiveDecisionService;
use App\Domains\Executive\Decision\ManagementResponseReadinessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class ExecutiveDecideManagementResponseCommandTest extends TestCase
{
    public function test_command_passes_explicit_specialist_requests_to_authoritative_executive_service(): void
    {
        $decision =
            new ExecutiveDecision(
                key: ManagementResponseReadinessPolicy::KEY,
                question: 'Does this explicit cross-domain specialist decision set support a bounded human management response now?',
                status: ExecutiveDecision::RECOMMENDED,
                recommendation: 'Proceed to human management review of this explicit cross-domain specialist decision set.',
                rationale: 'The explicit specialist decision set is established.',
                evidence: collect([
                    new ExecutiveDecisionEvidence(
                        source: 'test.executive',
                        description: 'Explicit cross-domain specialist support is established.',
                        position: ExecutiveDecisionEvidence::SUPPORTS,
                        confidence: 80
                    ),
                ]),
                constraints: collect(),
                confidence: 80,
                missingTruth: collect(),
                asOf: CarbonImmutable::parse(
                    '2026-09-05 11:30:00'
                )
            );

        $this->mock(
            ExecutiveDecisionService::class,
            function (
                MockInterface $mock
            ) use ($decision): void {
                $mock
                    ->shouldReceive(
                        'decide'
                    )
                    ->once()
                    ->withArgs(
                        function (
                            ExecutiveDecisionRequest $request
                        ): bool {
                            return $request->key
                                    === ManagementResponseReadinessPolicy::KEY
                                && $request->parameters === []
                                && $request->commercialRequest === null
                                && $request->cfoRequest instanceof CfoDecisionRequest
                                && $request->cfoRequest->key
                                    === DiscretionarySpendDecisionPolicy::KEY
                                && $request->cfoRequest->parameters['amount']
                                    === 5000.0
                                && $request->cfoRequest->parameters['currency']
                                    === 'GBP'
                                && $request->cfoRequest->parameters['recurring']
                                    === false
                                && $request->deliveryRequest instanceof DeliveryDecisionRequest
                                && $request->deliveryRequest->key
                                    === DeliveryEvidenceReviewReadinessPolicy::KEY
                                && $request->deliveryRequest->clientId
                                    === 'client-1';
                        }
                    )
                    ->andReturn(
                        $decision
                    );
            }
        );

        $exit =
            Artisan::call(
                'executive:decide-management-response',
                [
                    '--cfo-amount' => '5000',
                    '--delivery-client-id' => 'client-1',
                ]
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exit
        );

        $this->assertStringContainsString(
            'Executive OS Decision',
            $output
        );

        $this->assertStringContainsString(
            'Status: RECOMMENDED',
            $output
        );

        $this->assertStringContainsString(
            'Recommendation confidence: 80%',
            $output
        );

        $this->assertStringContainsString(
            'does not choose specialist requests',
            $output
        );
    }

    public function test_command_rejects_fewer_than_two_explicit_specialist_domains_before_service_execution(): void
    {
        $this->mock(
            ExecutiveDecisionService::class,
            fn (
                MockInterface $mock
            ) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        $exit =
            Artisan::call(
                'executive:decide-management-response',
                [
                    '--delivery-client-id' => 'client-1',
                ]
            );

        $this->assertSame(
            1,
            $exit
        );

        $this->assertStringContainsString(
            'requires at least two explicitly selected specialist decision domains',
            Artisan::output()
        );
    }
}
