<?php

namespace App\Domains\Commercial\Decision;

use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Domains\CommercialTruth\DTO\CurrentCommercialPosition;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class CommercialDecisionContext
{
    public function __construct(
        public CommercialDecisionRequest $request,
        public CurrentCommercialPosition $position,
        public ?ClientServiceCandidateAssessment $candidate,
        public ?string $candidateEvidenceFingerprint,
        public ?bool $candidateInReconciliationQueue,
        public CarbonImmutable $asOf,
    ) {
        /*
         * CommercialTruth is currently day-granular.
         *
         * Context requires every dated commercial observation to
         * describe the same commercial day. It does not manufacture
         * finer temporal precision than upstream truth provides.
         */
        if (
            $this->position->asOfDate
            !== $this->asOf->toDateString()
        ) {
            throw new InvalidArgumentException(
                'Commercial decision context position must match the context commercial date.'
            );
        }

        if (! $this->request->hasCandidateSubject()) {
            if ($this->candidate !== null) {
                throw new InvalidArgumentException(
                    'Commercial decision context cannot contain a candidate without an exact candidate subject.'
                );
            }

            if ($this->candidateEvidenceFingerprint !== null) {
                throw new InvalidArgumentException(
                    'Commercial decision context cannot contain an evidence fingerprint without an exact candidate subject.'
                );
            }

            if ($this->candidateInReconciliationQueue !== null) {
                throw new InvalidArgumentException(
                    'Commercial decision context cannot contain reconciliation-queue state without an exact candidate subject.'
                );
            }

            return;
        }

        if ($this->candidate === null) {
            throw new InvalidArgumentException(
                'Commercial decision candidate context requires the exact candidate assessment.'
            );
        }

        if (
            $this->candidateEvidenceFingerprint === null
            || trim($this->candidateEvidenceFingerprint) === ''
        ) {
            throw new InvalidArgumentException(
                'Commercial decision candidate context requires the exact evidence fingerprint.'
            );
        }

        if ($this->candidateInReconciliationQueue === null) {
            throw new InvalidArgumentException(
                'Commercial decision candidate context requires reconciliation-queue state.'
            );
        }

        if (
            $this->candidate
                ->candidate
                ->clientId
            !== $this->request->clientId
        ) {
            throw new InvalidArgumentException(
                'Commercial decision candidate client must match the requested client.'
            );
        }

        if (
            $this->candidate
                ->candidate
                ->fingerprint
            !== $this->request->candidateFingerprint
        ) {
            throw new InvalidArgumentException(
                'Commercial decision candidate fingerprint must match the requested candidate.'
            );
        }

        if (
            $this->candidateEvidenceFingerprint
            !== $this->request->evidenceFingerprint
        ) {
            throw new InvalidArgumentException(
                'Commercial decision candidate evidence fingerprint must match the requested evidence.'
            );
        }

        if (
            $this->candidate->asOfDate
            !== $this->asOf->toDateString()
        ) {
            throw new InvalidArgumentException(
                'Commercial decision candidate assessment must match the context commercial date.'
            );
        }

        if (
            $this->candidateInReconciliationQueue
            && $this->candidate->promotionReadiness
                !== 'ready_for_review'
        ) {
            throw new InvalidArgumentException(
                'Commercial decision context cannot mark a non-review-ready candidate as present in the reconciliation queue.'
            );
        }
    }

    public function hasCandidateSubject(): bool
    {
        return $this->candidate !== null;
    }
}
