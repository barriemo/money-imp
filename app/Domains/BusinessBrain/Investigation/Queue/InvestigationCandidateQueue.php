<?php

namespace App\Domains\BusinessBrain\Investigation\Queue;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use Illuminate\Support\Collection;

class InvestigationCandidateQueue
{
    public function __construct(
        public Collection $readyNow,

        public Collection $waitingForEvidence,

        public Collection $lowerPriority,

        public ?InvestigationCandidate $bestNext = null
    ) {}

    public function total(): int
    {
        return
            $this->readyNow->count()
            + $this->waitingForEvidence->count()
            + $this->lowerPriority->count();
    }
}
