<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruth;
use App\Domains\Delivery\Decision\DeliveryDecision;
use App\Domains\Delivery\Decision\DeliveryDecisionContext;
use App\Domains\Delivery\Decision\DeliveryDecisionContextService;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryDecisionService;
use App\Domains\Delivery\Decision\DeliveryEvidenceReviewReadinessPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Mockery\MockInterface;
use Tests\TestCase;

class DeliveryDecisionServiceTest extends TestCase
{
    public function test_supported_request_is_contextualised_once_and_decided_by_authoritative_policy(): void
    {
        $request =
            new DeliveryDecisionRequest(
                key: DeliveryEvidenceReviewReadinessPolicy::KEY,

                question: 'Should the recorded delivery evidence for this exact client proceed to human delivery review now?',

                clientId: 'client-1'
            );

        /*
         * Deliberately preserve zero invoice linkage while WorkLog
         * evidence exists. Review readiness is about recorded evidence
         * presence, not invoice-linkage confidence.
         */
        $truth =
            new DeliveryTruth(
                clientId: 'client-1',

                client: 'Client One',

                workLogCount: 1,

                invoicedWorkLogCount: 0,

                uninvoicedWorkLogCount: 1,

                commercialValue: 95.0,

                invoicedCommercialValue: 0.0,

                uninvoicedCommercialValue: 95.0,

                invoiceLinkageConfidence: 0
            );

        $observedAt =
            CarbonImmutable::parse(
                '2026-09-05 09:30:00'
            );

        $context =
            new DeliveryDecisionContext(
                request: $request,

                deliveryTruth: $truth,

                hasRecordedDeliveryEvidence: true,

                observedAt: $observedAt
            );

        $this->mock(
            DeliveryDecisionContextService::class,
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
                            DeliveryDecisionRequest $candidate
                        ): bool => $candidate === $request
                    )
                    ->andReturn(
                        $context
                    );
            }
        );

        $decision =
            app(
                DeliveryDecisionService::class
            )->decide(
                $request
            );

        $this->assertSame(
            DeliveryDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            'Proceed to human review of the recorded delivery evidence for this client.',
            $decision->recommendation
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertSame(
            $observedAt,
            $decision->asOf
        );

        $this->assertSame(
            0,
            $truth->invoiceLinkageConfidence
        );
    }

    public function test_unsupported_request_fails_before_context_is_built(): void
    {
        $request =
            new DeliveryDecisionRequest(
                key: 'delivery_health',

                question: 'Is this client delivery healthy?',

                clientId: 'client-1'
            );

        $this->mock(
            DeliveryDecisionContextService::class,
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
            'Delivery OS v1 has no authoritative policy for decision request delivery_health.'
        );

        app(
            DeliveryDecisionService::class
        )->decide(
            $request
        );
    }
}
