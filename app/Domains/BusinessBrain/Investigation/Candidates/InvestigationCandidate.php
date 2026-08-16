<?php

namespace App\Domains\BusinessBrain\Investigation\Candidates;

class InvestigationCandidate
{
    public function __construct(
        public string $type,

        public string $subjectType,

        public string $subjectId,

        public string $subjectName,

        public string $title,

        public string $question,

        public string $classification,

        public int $priority,

        public int $confidence,

        public array $reasons = [],

        public array $actions = [],

        public array $metadata = []
    ) {}
}
