<?php

namespace App\Domains\Executive;

class ExecutiveAnswer
{
    public function __construct(
        public string $questionType,
        public string $assessment,
        public int $confidence,
        public string $summary,
        public array $metrics = [],
        public array $risks = [],
        public array $missingEvidence = []
    ) {}
}
