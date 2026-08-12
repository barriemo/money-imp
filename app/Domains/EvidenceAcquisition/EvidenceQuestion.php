<?php

namespace App\Domains\EvidenceAcquisition;

class EvidenceQuestion
{
    public function __construct(
        public readonly string $question,
        public readonly string $reason,
        public readonly int $priority,
        public readonly string $domain,
        public readonly array $evidence = [],
    ) {}
}
