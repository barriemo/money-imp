<?php

namespace App\Domains\BusinessBrain\Investigation\Opening;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\Queue\InvestigationCandidateQueueService;
use App\Models\InvestigationCase;

class InvestigationCandidateOpener
{
    public function __construct(
        private InvestigationCandidateQueueService $queue,

        private InvestigationCaseService $cases
    ) {}

    public function next(): ?InvestigationCase
    {
        $candidate =
            $this->queue
                ->current()
                ->bestNext;

        if (! $candidate) {
            return null;
        }

        return $this->open(
            $candidate
        );
    }

    public function open(
        InvestigationCandidate $candidate
    ): InvestigationCase {
        return $this->cases
            ->findOrOpenForSubject(
                type: $candidate->type,

                subjectType: $candidate->subjectType,

                subjectId: $candidate->subjectId,

                subjectName: $candidate->subjectName,

                title: $candidate->title,

                question: $candidate->question
            );
    }
}
