<?php

namespace App\Domains\BusinessBrain\Investigation\Queue;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidateService;

class InvestigationCandidateQueueService
{
    public function __construct(
        private InvestigationCandidateService $candidates
    ) {}

    public function current(): InvestigationCandidateQueue
    {
        $candidates =
            $this->candidates
                ->current()
                ->values();

        $readyNow =
            $candidates
                ->filter(
                    fn (InvestigationCandidate $candidate) => $this->bucket(
                        $candidate
                    ) === InvestigationCandidateBucket::ReadyNow
                )
                ->sort(
                    fn (
                        InvestigationCandidate $left,
                        InvestigationCandidate $right
                    ) => [
                        $right->confidence,
                        $right->priority,
                    ]
                        <=>
                        [
                            $left->confidence,
                            $left->priority,
                        ]
                )
                ->values();

        $waitingForEvidence =
            $candidates
                ->filter(
                    fn (InvestigationCandidate $candidate) => $this->bucket(
                        $candidate
                    ) === InvestigationCandidateBucket::WaitingForEvidence
                )
                ->sortByDesc(
                    'priority'
                )
                ->values();

        $lowerPriority =
            $candidates
                ->filter(
                    fn (InvestigationCandidate $candidate) => $this->bucket(
                        $candidate
                    ) === InvestigationCandidateBucket::LowerPriority
                )
                ->sortByDesc(
                    'priority'
                )
                ->values();

        return new InvestigationCandidateQueue(
            readyNow: $readyNow,

            waitingForEvidence: $waitingForEvidence,

            lowerPriority: $lowerPriority,

            bestNext: $readyNow->first()
        );
    }

    public function bucket(
        InvestigationCandidate $candidate
    ): InvestigationCandidateBucket {
        if (
            in_array(
                $candidate->classification,
                [
                    'high_confidence_anomaly',
                    'accounting_ahead_of_bank',
                    'bank_ahead_of_accounting',
                ],
                true
            )
            && $candidate->confidence >= 80
        ) {
            return InvestigationCandidateBucket::ReadyNow;
        }

        if (
            $candidate->classification
                === 'historical_evidence_incomplete'
        ) {
            return InvestigationCandidateBucket::WaitingForEvidence;
        }

        if (
            $candidate->classification
                === 'cash_without_invoice_evidence'
            && $candidate->confidence < 80
        ) {
            return InvestigationCandidateBucket::WaitingForEvidence;
        }

        return InvestigationCandidateBucket::LowerPriority;
    }
}
