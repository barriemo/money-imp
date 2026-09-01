<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Models\ClientServiceReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class ClientServiceReconciliationQueueService
{
    public function __construct(
        private readonly ClientServiceCandidateAssessmentService $assessments,
        private readonly ClientServiceCandidateEvidenceFingerprint $evidenceFingerprint,
    ) {}

    public function ready(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??= CarbonImmutable::today();

        return $this->assessments
            ->all($asOf)
            ->filter(
                fn (
                    ClientServiceCandidateAssessment $assessment
                ) => $assessment
                    ->candidate
                    ->isServiceCandidate()
                    && $assessment
                        ->promotionReadiness
                        === 'ready_for_review'
            )
            ->filter(
                fn (
                    ClientServiceCandidateAssessment $assessment
                ) => ! $this->resolved(
                    $assessment
                )
            )
            ->values();
    }

    private function resolved(
        ClientServiceCandidateAssessment $assessment
    ): bool {
        $candidate =
            $assessment->candidate;

        $evidenceFingerprint =
            $this->evidenceFingerprint
                ->forCandidate(
                    $candidate
                );

        $latest =
            ClientServiceReconciliation::query()
                ->where(
                    'client_id',
                    $candidate->clientId
                )
                ->where(
                    'candidate_fingerprint',
                    $candidate->fingerprint
                )
                ->where(
                    'evidence_fingerprint',
                    $evidenceFingerprint
                )
                ->latest(
                    'reviewed_at'
                )
                ->latest(
                    'created_at'
                )
                ->first();

        if ($latest === null) {
            return false;
        }

        /*
         * A defer is deliberately non-terminal.
         *
         * It records that a human considered the candidate,
         * but it remains in the review queue.
         *
         * Rejection resolves this exact evidence set.
         * New invoice evidence produces a new evidence
         * fingerprint and may surface the candidate again.
         */
        return $latest->decision
            === 'rejected';
    }
}
