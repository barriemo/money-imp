<?php

namespace App\Domains\Commercial\Decision;

use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use InvalidArgumentException;

class ServiceReconciliationReadinessPolicy
{
    public const KEY =
        'service_reconciliation_readiness';

    public function supports(
        CommercialDecisionRequest $request
    ): bool {
        return $request->key === self::KEY;
    }

    public function decide(
        CommercialDecisionContext $context
    ): CommercialDecision {
        $candidate =
            $this->validatedCandidate(
                $context
            );

        return match (
            $candidate->promotionReadiness
        ) {
            'ready_for_review' => $context->candidateInReconciliationQueue
                    ? $this->recommendReconciliation(
                        $context
                    )
                    : $this->recommendDoNotReconcileOutsideQueue(
                        $context
                    ),

            'needs_more_evidence' => $this->deferForMissingEvidence(
                $context
            ),

            'needs_commercial_review' => $this->recommendSeparateCommercialReview(
                $context
            ),

            'not_service_candidate' => $this->recommendNotServiceCandidate(
                $context
            ),

            default => $this->deferUnsupportedReadinessState(
                $context
            ),
        };
    }

    private function validatedCandidate(
        CommercialDecisionContext $context
    ): ClientServiceCandidateAssessment {
        if (! $this->supports($context->request)) {
            throw new InvalidArgumentException(
                'Service reconciliation readiness policy cannot decide a different commercial decision request.'
            );
        }

        if ($context->request->parameters !== []) {
            throw new InvalidArgumentException(
                'Service reconciliation readiness request does not accept additional parameters.'
            );
        }

        if (
            ! $context->request->hasCandidateSubject()
            || ! $context->hasCandidateSubject()
            || $context->candidate === null
            || $context->candidateEvidenceFingerprint === null
            || $context->candidateInReconciliationQueue === null
        ) {
            throw new InvalidArgumentException(
                'Service reconciliation readiness requires an exact commercial candidate context.'
            );
        }

        return $context->candidate;
    }

    private function recommendReconciliation(
        CommercialDecisionContext $context
    ): CommercialDecision {
        return new CommercialDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CommercialDecision::RECOMMENDED,

            recommendation: 'Proceed with human service reconciliation for this exact commercial evidence set.',

            rationale: 'The exact candidate is assessed ready for review and is currently present in the authoritative service reconciliation queue.',

            evidence: collect([
                $this->requestEvidence(
                    $context
                ),

                $this->assessmentEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::SUPPORTS
                ),

                $this->queueEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::SUPPORTS
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $context->asOf
        );
    }

    private function recommendDoNotReconcileOutsideQueue(
        CommercialDecisionContext $context
    ): CommercialDecision {
        return new CommercialDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CommercialDecision::RECOMMENDED,

            recommendation: 'Do not proceed with human service reconciliation for this exact commercial evidence set at this time.',

            rationale: 'The candidate assessment is review-ready, but the exact evidence set is not present in the authoritative reconciliation queue. Queue membership is the current authority for whether this exact evidence set should proceed to reconciliation.',

            evidence: collect([
                $this->requestEvidence(
                    $context
                ),

                $this->assessmentEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::CONTEXT
                ),

                $this->queueEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::SUPPORTS
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $context->asOf
        );
    }

    private function deferForMissingEvidence(
        CommercialDecisionContext $context
    ): CommercialDecision {
        return new CommercialDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CommercialDecision::DEFERRED,

            recommendation: null,

            rationale: 'The exact candidate is assessed as needing more evidence. Missing commercial truth is preserved rather than converted into a positive or negative service-reconciliation recommendation.',

            evidence: collect([
                $this->requestEvidence(
                    $context
                ),

                $this->assessmentEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::CONTEXT
                ),

                $this->queueEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::CONTEXT
                ),
            ]),

            constraints: collect([
                new CommercialDecisionConstraint(
                    code: 'commercial_evidence_incomplete',

                    description: 'Sufficient commercial evidence must be established before service reconciliation readiness can be decided for this exact evidence set.',

                    type: CommercialDecisionConstraint::BLOCKING,

                    source: 'commercial_truth.candidate_assessment.promotion_readiness',

                    confidence: 100,

                    metadata: [
                        'promotion_readiness' => $context
                            ->candidate
                            ->promotionReadiness,
                    ]
                ),
            ]),

            confidence: 0,

            missingTruth: collect([
                'Commercial evidence sufficient to establish service reconciliation readiness for this exact candidate is not yet established.',
            ]),

            asOf: $context->asOf
        );
    }

    private function recommendSeparateCommercialReview(
        CommercialDecisionContext $context
    ): CommercialDecision {
        return new CommercialDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CommercialDecision::RECOMMENDED,

            recommendation: 'Do not proceed to human service reconciliation now; this exact commercial evidence set requires separate human commercial review first.',

            rationale: 'The upstream commercial assessment establishes that this is composite commercial evidence requiring human commercial interpretation before canonical service reconciliation.',

            evidence: collect([
                $this->requestEvidence(
                    $context
                ),

                $this->assessmentEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::SUPPORTS
                ),

                $this->queueEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::CONTEXT
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $context->asOf
        );
    }

    private function recommendNotServiceCandidate(
        CommercialDecisionContext $context
    ): CommercialDecision {
        return new CommercialDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CommercialDecision::RECOMMENDED,

            recommendation: 'Do not proceed to human service reconciliation for this exact commercial evidence set.',

            rationale: 'The upstream commercial assessment establishes that this evidence is not eligible for canonical client service review.',

            evidence: collect([
                $this->requestEvidence(
                    $context
                ),

                $this->assessmentEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::SUPPORTS
                ),

                $this->queueEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::CONTEXT
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $context->asOf
        );
    }

    private function deferUnsupportedReadinessState(
        CommercialDecisionContext $context
    ): CommercialDecision {
        return new CommercialDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CommercialDecision::DEFERRED,

            recommendation: null,

            rationale: 'The current commercial assessment state is not recognised by this bounded reconciliation-readiness policy, so the policy fails closed.',

            evidence: collect([
                $this->requestEvidence(
                    $context
                ),

                $this->assessmentEvidence(
                    context: $context,

                    position: CommercialDecisionEvidence::CONTEXT
                ),
            ]),

            constraints: collect([
                new CommercialDecisionConstraint(
                    code: 'unsupported_reconciliation_readiness_state',

                    description: 'The candidate readiness state must be recognised before a service-reconciliation recommendation can be established.',

                    type: CommercialDecisionConstraint::BLOCKING,

                    source: 'commercial_truth.candidate_assessment.promotion_readiness',

                    confidence: 100,

                    metadata: [
                        'promotion_readiness' => $context
                            ->candidate
                            ->promotionReadiness,
                    ]
                ),
            ]),

            confidence: 0,

            missingTruth: collect([
                'A recognised commercial reconciliation-readiness state is required.',
            ]),

            asOf: $context->asOf
        );
    }

    private function requestEvidence(
        CommercialDecisionContext $context
    ): CommercialDecisionEvidence {
        return new CommercialDecisionEvidence(
            source: 'commercial_decision_request.exact_subject',

            description: 'The request identifies one exact commercial evidence set by client, candidate fingerprint and evidence fingerprint.',

            position: CommercialDecisionEvidence::CONTEXT,

            confidence: 100,

            metadata: [
                'client_id' => $context->request->clientId,

                'candidate_fingerprint' => $context->request->candidateFingerprint,

                'evidence_fingerprint' => $context->request->evidenceFingerprint,
            ]
        );
    }

    private function assessmentEvidence(
        CommercialDecisionContext $context,
        string $position,
    ): CommercialDecisionEvidence {
        $candidate =
            $context->candidate;

        return new CommercialDecisionEvidence(
            source: 'commercial_truth.candidate_assessment.promotion_readiness',

            description: sprintf(
                'The exact candidate is assessed as %s.',
                str_replace(
                    '_',
                    ' ',
                    $candidate->promotionReadiness
                )
            ),

            position: $position,

            confidence: 100,

            metadata: [
                'promotion_readiness' => $candidate->promotionReadiness,

                'commercial_treatment' => $candidate
                    ->candidate
                    ->commercialTreatment,

                'service_type' => $candidate
                    ->candidate
                    ->serviceType,

                'freshness' => $candidate->freshness,
            ]
        );
    }

    private function queueEvidence(
        CommercialDecisionContext $context,
        string $position,
    ): CommercialDecisionEvidence {
        $present =
            $context->candidateInReconciliationQueue;

        return new CommercialDecisionEvidence(
            source: 'commercial_truth.service_reconciliation_queue',

            description: $present
                    ? 'The exact evidence set is present in the authoritative human service reconciliation queue.'
                    : 'The exact evidence set is not present in the authoritative human service reconciliation queue.',

            position: $position,

            confidence: 100,

            metadata: [
                'queue_present' => $present,
            ]
        );
    }
}
