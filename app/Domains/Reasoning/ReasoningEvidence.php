<?php

namespace App\Domains\Reasoning;

class ReasoningEvidence
{
    public function __construct(
        public string $nodeKey,
        public string $summary,
        public int $confidence = 100,
        public array $metadata = []
    ) {}
}
