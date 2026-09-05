<?php

namespace Tests\Feature;

use App\Domains\Billing\Decision\BillingDecision;
use App\Domains\Billing\Decision\BillingDecisionContext;
use App\Domains\Billing\Decision\BillingDecisionContextService;
use App\Domains\Billing\Decision\BillingDecisionRequest;
use App\Domains\Billing\Decision\BillingDecisionService;
use App\Domains\Billing\Decision\BillingEvidenceConclusionReadinessPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class BillingDecisionServiceTest extends TestCase
{
    public function test_supported_request_is_contextualised_once_and_decided_by_authoritative_policy(): void
    {
        $request =
            $this->request();

        $context =
            new BillingDecisionContext(
                request: $request,

                clientId: $this->clientId(),

                clientName: 'Exact Billing Client',

                serviceName: 'Website Hosting',

                serviceStatus: 'active',

                observedBilling: null,

                truthBoundary: BillingDecisionContext::TRUTH_BOUNDARY,

                observedAt: CarbonImmutable::parse(
                    '2026-09-05 12:00:00'
                )
            );

        $contexts =
            Mockery::mock(
                BillingDecisionContextService::class
            );

        $contexts
            ->shouldReceive(
                'forDecision'
            )
            ->once()
            ->with(
                $request
            )
            ->andReturn(
                $context
            );

        $service =
            new BillingDecisionService(
                $contexts,
                new BillingEvidenceConclusionReadinessPolicy
            );

        $decision =
            $service->decide(
                $request
            );

        $this->assertSame(
            BillingDecision::STATUS_RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            BillingEvidenceConclusionReadinessPolicy::KEY,
            $decision->key
        );

        $this->assertStringContainsString(
            'no canonical observed billing is established',
            strtolower(
                $decision->recommendation
            )
        );

        $this->assertStringContainsString(
            'does not establish that no billing obligation exists',
            strtolower(
                $decision->rationale
            )
        );
    }

    public function test_unsupported_request_fails_before_context_assembly(): void
    {
        $contexts =
            Mockery::mock(
                BillingDecisionContextService::class
            );

        $contexts
            ->shouldNotReceive(
                'forDecision'
            );

        $service =
            new BillingDecisionService(
                $contexts,
                new BillingEvidenceConclusionReadinessPolicy
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Billing OS v1 has no authoritative policy for decision request unsupported.'
        );

        $service->decide(
            new BillingDecisionRequest(
                key: 'unsupported',

                question: 'Unsupported Billing question.',

                clientServiceId: $this->clientServiceId()
            )
        );
    }

    private function request(): BillingDecisionRequest
    {
        return new BillingDecisionRequest(
            key: BillingEvidenceConclusionReadinessPolicy::KEY,

            question: 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?',

            clientServiceId: $this->clientServiceId()
        );
    }

    private function clientServiceId(): string
    {
        return '00000000-0000-4000-8000-000000000001';
    }

    private function clientId(): string
    {
        return '00000000-0000-4000-8000-000000000010';
    }
}
