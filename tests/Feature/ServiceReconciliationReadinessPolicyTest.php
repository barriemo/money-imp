<?php

namespace Tests\Feature;

use App\Domains\Commercial\Decision\CommercialDecision;
use App\Domains\Commercial\Decision\CommercialDecisionConstraint;
use App\Domains\Commercial\Decision\CommercialDecisionContext;
use App\Domains\Commercial\Decision\CommercialDecisionEvidence;
use App\Domains\Commercial\Decision\CommercialDecisionRequest;
use App\Domains\Commercial\Decision\ServiceReconciliationReadinessPolicy;
use App\Domains\CommercialTruth\DTO\ClientServiceCandidate;
use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Domains\CommercialTruth\DTO\CurrentCommercialPosition;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class ServiceReconciliationReadinessPolicyTest extends TestCase
{
    public function test_policy_supports_only_its_bounded_request_key(): void
    {
        $policy =
            new ServiceReconciliationReadinessPolicy;

        $this->assertTrue(
            $policy->supports(
                $this->request()
            )
        );

        $this->assertFalse(
            $policy->supports(
                $this->request(
                    key: 'other_commercial_question'
                )
            )
        );
    }

    public function test_policy_rejects_different_request_and_non_candidate_context(): void
    {
        $policy =
            new ServiceReconciliationReadinessPolicy;

        try {
            $policy->decide(
                $this->context(
                    request: $this->request(
                        key: 'other_commercial_question'
                    )
                )
            );

            $this->fail(
                'Expected different request key to fail.'
            );
        } catch (InvalidArgumentException) {
            $this->assertTrue(
                true
            );
        }

        $this->expectException(
            InvalidArgumentException::class
        );

        $policy->decide(
            new CommercialDecisionContext(
                request: new CommercialDecisionRequest(
                    key: ServiceReconciliationReadinessPolicy::KEY,

                    question: 'Question.'
                ),

                position: $this->position(),

                candidate: null,

                candidateEvidenceFingerprint: null,

                candidateInReconciliationQueue: null,

                asOf: $this->asOf()
            )
        );
    }

    public function test_policy_rejects_unused_parameters(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Service reconciliation readiness request does not accept additional parameters.'
        );

        $policy =
            new ServiceReconciliationReadinessPolicy;

        $policy->decide(
            $this->context(
                request: $this->request(
                    parameters: [
                        'priority' => 'high',
                    ]
                )
            )
        );
    }

    public function test_ready_and_authoritatively_queued_subject_is_recommended_for_human_reconciliation(): void
    {
        $decision =
            (new ServiceReconciliationReadinessPolicy)
                ->decide(
                    $this->context(
                        promotionReadiness: 'ready_for_review',

                        queuePresent: true
                    )
                );

        $this->assertSame(
            CommercialDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertStringStartsWith(
            'Proceed with human service reconciliation',
            $decision->recommendation
        );

        $this->assertTrue(
            $decision->constraints->isEmpty()
        );

        $this->assertTrue(
            $decision->missingTruth->isEmpty()
        );

        $this->assertTrue(
            $decision->evidence
                ->contains(
                    fn (CommercialDecisionEvidence $evidence): bool => $evidence->source
                            === 'commercial_truth.service_reconciliation_queue'
                        && $evidence->position
                            === CommercialDecisionEvidence::SUPPORTS
                        && $evidence->metadata['queue_present']
                            === true
                )
        );
    }

    public function test_review_ready_subject_outside_authoritative_queue_gets_established_negative_guidance(): void
    {
        $decision =
            (new ServiceReconciliationReadinessPolicy)
                ->decide(
                    $this->context(
                        promotionReadiness: 'ready_for_review',

                        queuePresent: false
                    )
                );

        $this->assertSame(
            CommercialDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertStringStartsWith(
            'Do not proceed with human service reconciliation',
            $decision->recommendation
        );

        $this->assertTrue(
            $decision->missingTruth->isEmpty()
        );

        $this->assertTrue(
            $decision->evidence
                ->contains(
                    fn (CommercialDecisionEvidence $evidence): bool => $evidence->source
                            === 'commercial_truth.service_reconciliation_queue'
                        && $evidence->position
                            === CommercialDecisionEvidence::SUPPORTS
                        && $evidence->metadata['queue_present']
                            === false
                )
        );
    }

    public function test_needs_more_evidence_is_deferred_without_converting_unknown_truth_into_negative_guidance(): void
    {
        $decision =
            (new ServiceReconciliationReadinessPolicy)
                ->decide(
                    $this->context(
                        promotionReadiness: 'needs_more_evidence',

                        queuePresent: false
                    )
                );

        $this->assertSame(
            CommercialDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertSame(
            0,
            $decision->confidence
        );

        $this->assertFalse(
            $decision->missingTruth->isEmpty()
        );

        $this->assertTrue(
            $decision->constraints
                ->contains(
                    fn (CommercialDecisionConstraint $constraint): bool => $constraint->code
                            === 'commercial_evidence_incomplete'
                        && $constraint->type
                            === CommercialDecisionConstraint::BLOCKING
                )
        );
    }

    public function test_composite_candidate_gets_established_negative_service_reconciliation_guidance(): void
    {
        $decision =
            (new ServiceReconciliationReadinessPolicy)
                ->decide(
                    $this->context(
                        promotionReadiness: 'needs_commercial_review',

                        queuePresent: false,

                        commercialTreatment: 'composite_candidate',

                        serviceType: 'composite'
                    )
                );

        $this->assertSame(
            CommercialDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertStringContainsString(
            'requires separate human commercial review first',
            $decision->recommendation
        );

        $this->assertTrue(
            $decision->constraints->isEmpty()
        );

        $this->assertTrue(
            $decision->missingTruth->isEmpty()
        );
    }

    public function test_not_service_candidate_gets_established_negative_guidance(): void
    {
        $decision =
            (new ServiceReconciliationReadinessPolicy)
                ->decide(
                    $this->context(
                        promotionReadiness: 'not_service_candidate',

                        queuePresent: false,

                        commercialTreatment: 'unknown',

                        serviceType: 'other'
                    )
                );

        $this->assertSame(
            CommercialDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertStringStartsWith(
            'Do not proceed to human service reconciliation',
            $decision->recommendation
        );

        $this->assertTrue(
            $decision->constraints->isEmpty()
        );

        $this->assertTrue(
            $decision->missingTruth->isEmpty()
        );
    }

    public function test_unrecognised_readiness_state_fails_closed_as_deferred(): void
    {
        $decision =
            (new ServiceReconciliationReadinessPolicy)
                ->decide(
                    $this->context(
                        promotionReadiness: 'future_unrecognised_state',

                        queuePresent: false
                    )
                );

        $this->assertSame(
            CommercialDecision::DEFERRED,
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
                    fn (CommercialDecisionConstraint $constraint): bool => $constraint->code
                            === 'unsupported_reconciliation_readiness_state'
                        && $constraint->type
                            === CommercialDecisionConstraint::BLOCKING
                )
        );
    }

    public function test_policy_does_not_manufacture_conditional_decisions(): void
    {
        $cases = [
            [
                'readiness' => 'ready_for_review',

                'queue' => true,
            ],

            [
                'readiness' => 'ready_for_review',

                'queue' => false,
            ],

            [
                'readiness' => 'needs_more_evidence',

                'queue' => false,
            ],

            [
                'readiness' => 'needs_commercial_review',

                'queue' => false,
            ],

            [
                'readiness' => 'not_service_candidate',

                'queue' => false,
            ],

            [
                'readiness' => 'future_unrecognised_state',

                'queue' => false,
            ],
        ];

        foreach ($cases as $case) {
            $decision =
                (new ServiceReconciliationReadinessPolicy)
                    ->decide(
                        $this->context(
                            promotionReadiness: $case['readiness'],

                            queuePresent: $case['queue']
                        )
                    );

            $this->assertNotSame(
                CommercialDecision::CONDITIONAL,
                $decision->status
            );
        }
    }

    public function test_policy_preserves_exact_request_identity_in_context_evidence(): void
    {
        $decision =
            (new ServiceReconciliationReadinessPolicy)
                ->decide(
                    $this->context()
                );

        $requestEvidence =
            $decision->evidence
                ->first(
                    fn (CommercialDecisionEvidence $evidence): bool => $evidence->source
                            === 'commercial_decision_request.exact_subject'
                );

        $this->assertInstanceOf(
            CommercialDecisionEvidence::class,
            $requestEvidence
        );

        $this->assertSame(
            'client-1',
            $requestEvidence->metadata['client_id']
        );

        $this->assertSame(
            'candidate-1',
            $requestEvidence->metadata['candidate_fingerprint']
        );

        $this->assertSame(
            'evidence-1',
            $requestEvidence->metadata['evidence_fingerprint']
        );
    }

    public function test_policy_decision_confidence_is_not_derived_from_upstream_classification_or_cadence_scores(): void
    {
        $decision =
            (new ServiceReconciliationReadinessPolicy)
                ->decide(
                    $this->context(
                        classificationConfidence: 12,

                        cadenceConfidence: 34
                    )
                );

        $this->assertSame(
            CommercialDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertSame(
            12,
            $decision->evidence
                ->first(
                    fn (CommercialDecisionEvidence $evidence): bool => $evidence->source
                            === 'commercial_truth.candidate_assessment.promotion_readiness'
                )
                ->metadata['classification_confidence']
                ?? 12
        );
    }

    private function context(
        ?CommercialDecisionRequest $request = null,
        string $promotionReadiness = 'ready_for_review',
        bool $queuePresent = true,
        string $commercialTreatment = 'service_candidate',
        string $serviceType = 'hosting',
        int $classificationConfidence = 95,
        int $cadenceConfidence = 99,
    ): CommercialDecisionContext {
        return new CommercialDecisionContext(
            request: $request
                ?? $this->request(),

            position: $this->position(),

            candidate: $this->assessment(
                promotionReadiness: $promotionReadiness,

                commercialTreatment: $commercialTreatment,

                serviceType: $serviceType,

                classificationConfidence: $classificationConfidence,

                cadenceConfidence: $cadenceConfidence
            ),

            candidateEvidenceFingerprint: 'evidence-1',

            candidateInReconciliationQueue: $queuePresent,

            asOf: $this->asOf()
        );
    }

    private function request(
        string $key = ServiceReconciliationReadinessPolicy::KEY,
        array $parameters = [],
    ): CommercialDecisionRequest {
        return new CommercialDecisionRequest(
            key: $key,

            question: 'Should this exact commercial evidence set proceed to human service reconciliation now?',

            clientId: 'client-1',

            candidateFingerprint: 'candidate-1',

            evidenceFingerprint: 'evidence-1',

            parameters: $parameters
        );
    }

    private function assessment(
        string $promotionReadiness,
        string $commercialTreatment,
        string $serviceType,
        int $classificationConfidence,
        int $cadenceConfidence,
    ): ClientServiceCandidateAssessment {
        return new ClientServiceCandidateAssessment(
            candidate: new ClientServiceCandidate(
                clientId: 'client-1',

                clientName: 'Example Client',

                serviceType: $serviceType,

                serviceHint: null,

                fingerprint: 'candidate-1',

                commercialTreatment: $commercialTreatment,

                evidenceCount: 3,

                invoiceItemIds: [
                    'invoice-item-1',
                    'invoice-item-2',
                    'invoice-item-3',
                ],

                signedObservedNet: 300.0,

                positiveObservedNet: 300.0,

                negativeObservedNet: 0.0,

                latestObservedUnitPrice: 100.0,

                firstObservedOn: '2026-07-01',

                lastObservedOn: '2026-09-01',

                cadence: 'monthly',

                monthlyEquivalent: 100.0,

                classificationConfidence: $classificationConfidence,

                cadenceConfidence: $cadenceConfidence
            ),

            asOfDate: '2026-09-04',

            daysSinceLastObservation: 3,

            freshness: 'current',

            cadenceEstablished: true,

            recurringEvidence: true,

            currentMonthlyEquivalent: 100.0,

            promotionReadiness: $promotionReadiness,

            reasons: [
                'Test commercial truth.',
            ]
        );
    }

    private function position(): CurrentCommercialPosition
    {
        return new CurrentCommercialPosition(
            asOfDate: '2026-09-04',

            serviceCandidateCount: 248,

            recurringCandidateCount: 131,

            currentRecurringCandidateCount: 76,

            supportedCurrentMonthlyEquivalent: 4422.87,

            recentlyObservedRecurringCandidateCount: 7,

            recentlyObservedMonthlyEquivalent: 1325.0,

            staleRecurringCandidateCount: 6,

            staleMonthlyEquivalent: 31.23,

            historicalRecurringCandidateCount: 42,

            historicalMonthlyEquivalent: 6536.5,

            readyForReviewCount: 83,

            needsMoreEvidenceCount: 165,

            sourceEvidenceItemCount: 0,

            currentEvidenceItemCount: 0,

            byServiceType: [],

            byClient: [],

            evidenceStatus: 'invoice_history_supported_not_reconciled',

            caveats: [],

            provenance: [],

            canonicalActiveServiceCount: 0,

            canonicalServicesWithObservedBillingCount: 0,

            canonicalCurrentRecurringServiceCount: 0,

            canonicalCurrentObservedMonthlyEquivalent: 0.0,

            unreconciledCurrentRecurringCandidateCount: 76,

            unreconciledCurrentMonthlyEquivalent: 4422.87,

            attributionReviewReadyCount: 0,

            billingRuleCount: 0,

            contractedMonthlyValue: null,

            contractedValueStatus: 'not_established'
        );
    }

    private function asOf(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            '2026-09-04 21:30:00'
        );
    }
}
