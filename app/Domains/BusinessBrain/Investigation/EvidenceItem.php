<?php

namespace App\Domains\BusinessBrain\Investigation;

class EvidenceItem
{
    public function __construct(
        public string $source,

        public string $description,

        public string $position,

        public int $confidence,

        public array $metadata = []
    ) {}
}
