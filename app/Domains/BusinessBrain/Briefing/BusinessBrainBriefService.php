<?php

namespace App\Domains\BusinessBrain\Briefing;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use App\Domains\BusinessBrain\Investigation\Queue\InvestigationCandidateQueueService;
use App\Models\BusinessExperience;
use App\Models\InvestigationCase;

class BusinessBrainBriefService
{
    public function __construct(
        private InvestigationCandidateQueueService $candidateQueue
    ) {}

    public function current(): BusinessBrainBrief
    {
        $active =
            InvestigationCase::query()
                ->whereIn(
                    'status',
                    [
                        'open',
                        'testing',
                        'waiting',
                    ]
                );

        $activeCases =
            (clone $active)
                ->get([
                    'status',
                    'confidence',
                ]);

        $queue =
            $this->candidateQueue
                ->current();

        $candidates =
            $queue->readyNow
                ->concat(
                    $queue->waitingForEvidence
                )
                ->concat(
                    $queue->lowerPriority
                )
                ->values();

        $highestConfidence =
            $candidates
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
                ->first();

        $highestImpact =
            $candidates
                ->sortByDesc(
                    fn (InvestigationCandidate $candidate) => abs(
                        (float) (
                            $candidate->metadata[
                                'ledger_difference'
                            ]
                            ?? 0
                        )
                    )
                )
                ->first();

        $averageConfidence =
            $activeCases->isEmpty()
                ? 0
                : (int) round(
                    $activeCases
                        ->avg(
                            'confidence'
                        )
                );

        return new BusinessBrainBrief(
            activeInvestigationCount: $activeCases->count(),

            waitingInvestigationCount: $activeCases
                ->where(
                    'status',
                    'waiting'
                )
                ->count(),

            candidateCount: $queue->total(),

            readyNowCount: $queue->readyNow->count(),

            waitingForEvidenceCandidateCount: $queue->waitingForEvidence->count(),

            lowerPriorityCandidateCount: $queue->lowerPriority->count(),

            recentlyClosedCount: InvestigationCase::query()
                ->where(
                    'status',
                    'closed'
                )
                ->where(
                    'closed_at',
                    '>=',
                    now()->subDays(7)
                )
                ->count(),

            experienceCount: BusinessExperience::query()
                ->count(),

            averageActiveConfidence: $averageConfidence,

            highestConfidenceCandidate: $highestConfidence,

            highestImpactCandidate: $highestImpact,

            bestNextCandidate: $queue->bestNext
        );
    }
}
