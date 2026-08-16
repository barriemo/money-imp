<?php

namespace App\Domains\BusinessBrain\Briefing;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;

class BusinessBrainBrief
{
    public function __construct(
        public int $activeInvestigationCount,

        public int $waitingInvestigationCount,

        public int $candidateCount,

        public int $readyNowCount,

        public int $waitingForEvidenceCandidateCount,

        public int $lowerPriorityCandidateCount,

        public int $recentlyClosedCount,

        public int $experienceCount,

        public int $averageActiveConfidence,

        public ?InvestigationCandidate $highestConfidenceCandidate,

        public ?InvestigationCandidate $highestImpactCandidate,

        public ?InvestigationCandidate $bestNextCandidate
    ) {}
}
