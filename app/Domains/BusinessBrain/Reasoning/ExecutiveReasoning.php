<?php

namespace App\Domains\BusinessBrain\Reasoning;

class ExecutiveReasoning
{
    public function __construct(
        public string $type,

        public ?string $clientId,

        public ?string $client,

        public string $title,

        public string $description,

        public ?float $estimatedFinancialImpact,

        public ?int $estimatedEffortMinutes,

        public int $confidence,

        public int $urgency,

        public int $score,

        public string $recommendedAction,

        public array $supportingEvidence = []
    ) {}
}
