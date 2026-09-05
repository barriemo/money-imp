<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruth;
use App\Domains\Delivery\Decision\DeliveryDecision;
use App\Domains\Delivery\Decision\DeliveryDecisionConstraint;
use App\Domains\Delivery\Decision\DeliveryDecisionContext;
use App\Domains\Delivery\Decision\DeliveryDecisionEvidence;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryEvidenceReviewReadinessPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class DeliveryEvidenceReviewReadinessPolicyTest extends TestCase
{
    public function test_policy_supports_only_bounded_delivery_evidence_review_request(): void
    {
        $policy =
            new DeliveryEvidenceReviewReadinessPolicy;

        $this->assertTrue(
            $policy->supports(
                $this->request()
            )
        );

        $this->assertFalse(
            $policy->supports(
                $this->request(
                    key: 'delivery_health'
                )
            )
        );

        $this->assertFalse(
            $policy->supports(
                $this->request(
                    key: 'invoice_work'
                )
            )
        );
    }

    public function test_policy_rejects_unsupported_request(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->policy()->decide(
            $this->context(
                request: $this->request(
                    key: 'another_delivery_decision'
                )
            )
        );
    }

    public function test_policy_specific_parameters_fail_closed(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->policy()->decide(
            $this->context(
                request: $this->request(
                    parameters: [
                        'threshold' => 10,
                    ]
                )
            )
        );
    }

    public function test_missing_recorded_delivery_evidence_defers(): void
    {
        $decision =
            $this->policy()->decide(
                $this->context()
            );

        $this->assertSame(
            DeliveryDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertSame(
            0,
            $decision->confidence
        );

        $this->assertCount(
            1,
            $decision->constraints
        );

        $this->assertSame(
            DeliveryDecisionConstraint::BLOCKING,
            $decision->constraints
                ->first()
                ->type
        );

        $this->assertSame(
            'recorded_delivery_evidence_missing',
            $decision->constraints
                ->first()
                ->code
        );

        $this->assertCount(
            1,
            $decision->missingTruth
        );

        $this->assertSame(
            'Recorded client-attributable delivery evidence for this client is missing.',
            $decision->missingTruth->first()
        );
    }

    public function test_empty_dataset_zeroes_do_not_become_established_zero_delivery(): void
    {
        $decision =
            $this->policy()->decide(
                $this->context(
                    commercialValue: 0.0,
                    uninvoicedCommercialValue: 0.0,
                    invoiceLinkageConfidence: 0
                )
            );

        $this->assertSame(
            DeliveryDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertStringContainsString(
            'evidence is recorded',
            $decision->rationale
        );

        $this->assertStringNotContainsString(
            'delivery is zero',
            strtolower(
                $decision->rationale
            )
        );

        $this->assertStringNotContainsString(
            'delivery is complete',
            strtolower(
                $decision->rationale
            )
        );

        $this->assertStringNotContainsString(
            'delivery is healthy',
            strtolower(
                $decision->rationale
            )
        );
    }

    public function test_recorded_delivery_evidence_is_recommended_for_human_review(): void
    {
        $decision =
            $this->policy()->decide(
                $this->context(
                    workLogCount: 2,
                    invoicedWorkLogCount: 1,
                    uninvoicedWorkLogCount: 1,
                    commercialValue: 190.0,
                    invoicedCommercialValue: 95.0,
                    uninvoicedCommercialValue: 95.0,
                    invoiceLinkageConfidence: 50
                )
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

        $this->assertCount(
            1,
            $decision->evidence
        );

        $support =
            $decision->evidence->first();

        $this->assertSame(
            DeliveryDecisionEvidence::SUPPORTS,
            $support->position
        );

        $this->assertSame(
            100,
            $support->confidence
        );

        $this->assertSame(
            2,
            $support->metadata[
                'work_log_count'
            ]
        );

        $this->assertTrue(
            $decision->constraints->isEmpty()
        );

        $this->assertTrue(
            $decision->missingTruth->isEmpty()
        );
    }

    public function test_invoice_linkage_confidence_does_not_become_decision_confidence(): void
    {
        $unlinked =
            $this->policy()->decide(
                $this->context(
                    workLogCount: 1,
                    uninvoicedWorkLogCount: 1,
                    commercialValue: 95.0,
                    uninvoicedCommercialValue: 95.0,
                    invoiceLinkageConfidence: 0
                )
            );

        $linked =
            $this->policy()->decide(
                $this->context(
                    workLogCount: 1,
                    invoicedWorkLogCount: 1,
                    commercialValue: 95.0,
                    invoicedCommercialValue: 95.0,
                    invoiceLinkageConfidence: 100
                )
            );

        $this->assertSame(
            DeliveryDecision::RECOMMENDED,
            $unlinked->status
        );

        $this->assertSame(
            DeliveryDecision::RECOMMENDED,
            $linked->status
        );

        $this->assertSame(
            100,
            $unlinked->confidence
        );

        $this->assertSame(
            100,
            $linked->confidence
        );
    }

    public function test_commercial_value_does_not_turn_review_readiness_into_recovery_or_invoice_guidance(): void
    {
        $small =
            $this->policy()->decide(
                $this->context(
                    workLogCount: 1,
                    uninvoicedWorkLogCount: 1,
                    commercialValue: 1.0,
                    uninvoicedCommercialValue: 1.0
                )
            );

        $large =
            $this->policy()->decide(
                $this->context(
                    workLogCount: 1,
                    uninvoicedWorkLogCount: 1,
                    commercialValue: 1000000.0,
                    uninvoicedCommercialValue: 1000000.0
                )
            );

        $this->assertSame(
            $small->status,
            $large->status
        );

        $this->assertSame(
            $small->recommendation,
            $large->recommendation
        );

        $this->assertSame(
            $small->confidence,
            $large->confidence
        );

        $this->assertStringContainsString(
            'does not establish',
            strtolower(
                $large->rationale
            )
        );

        $this->assertStringContainsString(
            'invoice readiness',
            strtolower(
                $large->rationale
            )
        );
    }

    public function test_policy_does_not_manufacture_conditional_decisions(): void
    {
        $withoutEvidence =
            $this->policy()->decide(
                $this->context()
            );

        $withEvidence =
            $this->policy()->decide(
                $this->context(
                    workLogCount: 1,
                    uninvoicedWorkLogCount: 1,
                    commercialValue: 95.0,
                    uninvoicedCommercialValue: 95.0
                )
            );

        $this->assertNotSame(
            DeliveryDecision::CONDITIONAL,
            $withoutEvidence->status
        );

        $this->assertNotSame(
            DeliveryDecision::CONDITIONAL,
            $withEvidence->status
        );
    }

    public function test_decision_preserves_exact_request_identity_and_context_time(): void
    {
        $request =
            $this->request(
                question: 'Should this exact client delivery evidence proceed to human review now?'
            );

        $observedAt =
            CarbonImmutable::parse(
                '2026-09-05 09:00:00'
            );

        $decision =
            $this->policy()->decide(
                $this->context(
                    request: $request,
                    observedAt: $observedAt,
                    workLogCount: 1,
                    uninvoicedWorkLogCount: 1,
                    commercialValue: 95.0,
                    uninvoicedCommercialValue: 95.0
                )
            );

        $this->assertSame(
            $request->key,
            $decision->key
        );

        $this->assertSame(
            $request->question,
            $decision->question
        );

        $this->assertTrue(
            $observedAt->equalTo(
                $decision->asOf
            )
        );
    }

    private function policy(): DeliveryEvidenceReviewReadinessPolicy
    {
        return new DeliveryEvidenceReviewReadinessPolicy;
    }

    private function request(
        string $key = DeliveryEvidenceReviewReadinessPolicy::KEY,
        string $question = 'Should this client recorded delivery evidence proceed to human delivery review now?',
        array $parameters = []
    ): DeliveryDecisionRequest {
        return new DeliveryDecisionRequest(
            key: $key,
            question: $question,
            clientId: 'client-1',
            parameters: $parameters
        );
    }

    private function context(
        ?DeliveryDecisionRequest $request = null,
        ?CarbonImmutable $observedAt = null,
        int $workLogCount = 0,
        int $invoicedWorkLogCount = 0,
        int $uninvoicedWorkLogCount = 0,
        float $commercialValue = 0.0,
        float $invoicedCommercialValue = 0.0,
        float $uninvoicedCommercialValue = 0.0,
        int $invoiceLinkageConfidence = 0
    ): DeliveryDecisionContext {
        $request ??=
            $this->request();

        $observedAt ??=
            CarbonImmutable::parse(
                '2026-09-05 09:00:00'
            );

        return new DeliveryDecisionContext(
            request: $request,
            deliveryTruth: new DeliveryTruth(
                clientId: $request->clientId,
                client: 'Delivery Policy Client',
                workLogCount: $workLogCount,
                invoicedWorkLogCount: $invoicedWorkLogCount,
                uninvoicedWorkLogCount: $uninvoicedWorkLogCount,
                commercialValue: $commercialValue,
                invoicedCommercialValue: $invoicedCommercialValue,
                uninvoicedCommercialValue: $uninvoicedCommercialValue,
                invoiceLinkageConfidence: $invoiceLinkageConfidence
            ),
            hasRecordedDeliveryEvidence: $workLogCount > 0,
            observedAt: $observedAt
        );
    }
}
