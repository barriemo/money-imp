<?php

namespace App\Domains\BusinessBrain\Investigation;

class InvestigationResult
{
    public function __construct(
        public Hypothesis $hypothesis,

        public string $status,

        public int $confidence,

        public array $evidence,

        public array $missingEvidence,

        public string $recommendation
    ) {}
}
