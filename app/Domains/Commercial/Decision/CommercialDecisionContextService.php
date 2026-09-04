<?php

namespace App\Domains\Commercial\Decision;

use App\Domains\CommercialTruth\Services\ClientServiceCandidateAssessmentService;
use App\Domains\CommercialTruth\Services\ClientServiceCandidateEvidenceFingerprint;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationQueueService;
use App\Domains\CommercialTruth\Services\CurrentCommercialPositionService;
use App\Models\Client;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class CommercialDecisionContextService
{
    public function __construct(
        private CurrentCommercialPositionService $positions,
        private ClientServiceCandidateAssessmentService $assessments,
        private ClientServiceCandidateEvidenceFingerprint $evidenceFingerprint,
        private ClientServiceReconciliationQueueService $reconciliationQueue,
    ) {}

    public function forDecision(
        CommercialDecisionRequest $request,
        ?CarbonImmutable $asOf = null
    ): CommercialDecisionContext {
        $asOf ??=
            CarbonImmutable::today();

        /*
         * Build one aggregate commercial position for the requested
         * commercial day.
         *
         * Context assembles available truth only. It does not convert
         * review readiness or upstream confidence metadata into
         * recommendation confidence or decision guidance.
         */
        $position =
            $this->positions
                ->position(
                    $asOf
                );

        if (! $request->hasClientSubject()) {
            return new CommercialDecisionContext(
                request: $request,
                position: $position,
                candidate: null,
                candidateEvidenceFingerprint: null,
                candidateInReconciliationQueue: null,
                asOf: $asOf
            );
        }

        $client =
            Client::query()
                ->find(
                    $request->clientId
                );

        if ($client === null) {
            throw new InvalidArgumentException(
                'Commercial decision subject client does not exist.'
            );
        }

        if (! $request->hasCandidateSubject()) {
            return new CommercialDecisionContext(
                request: $request,
                position: $position,
                candidate: null,
                candidateEvidenceFingerprint: null,
                candidateInReconciliationQueue: null,
                asOf: $asOf
            );
        }

        /*
         * Exact commercial candidate identity is:
         *
         * client id
         * + inferred candidate fingerprint
         * + exact invoice-item evidence fingerprint.
         *
         * Candidate fingerprint alone is deliberately insufficient:
         * composite / atomic evidence may share the same inferred
         * commercial family while representing different source sets.
         */
        $matches =
            $this->assessments
                ->forClient(
                    $client,
                    $asOf
                )
                ->filter(
                    fn ($assessment): bool => $assessment
                        ->candidate
                        ->fingerprint
                        === $request->candidateFingerprint
                        && $this->evidenceFingerprint
                            ->forCandidate(
                                $assessment->candidate
                            )
                            === $request->evidenceFingerprint
                )
                ->values();

        if ($matches->isEmpty()) {
            throw new InvalidArgumentException(
                'Commercial decision exact candidate does not exist in current commercial truth.'
            );
        }

        if ($matches->count() !== 1) {
            throw new InvalidArgumentException(
                'Commercial decision exact candidate identity is ambiguous.'
            );
        }

        $candidate =
            $matches->first();

        $candidateEvidenceFingerprint =
            $this->evidenceFingerprint
                ->forCandidate(
                    $candidate->candidate
                );

        $candidateInReconciliationQueue =
            $this->reconciliationQueue
                ->ready(
                    $asOf
                )
                ->contains(
                    fn ($assessment): bool => $assessment
                        ->candidate
                        ->clientId
                        === $request->clientId
                        && $assessment
                            ->candidate
                            ->fingerprint
                            === $request->candidateFingerprint
                            && $this->evidenceFingerprint
                                ->forCandidate(
                                    $assessment->candidate
                                )
                            === $request->evidenceFingerprint
                );

        return new CommercialDecisionContext(
            request: $request,
            position: $position,
            candidate: $candidate,
            candidateEvidenceFingerprint: $candidateEvidenceFingerprint,
            candidateInReconciliationQueue: $candidateInReconciliationQueue,
            asOf: $asOf
        );
    }
}
