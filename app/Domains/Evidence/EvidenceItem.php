<?php

namespace App\Domains\Evidence;

class EvidenceItem
{
    public function __construct(
        public string $type,
        public string $source,
        public string $summary,
        public int $confidence,
        public mixed $subject = null,
        public bool $verified = false,
        public array $metadata = []
    ) {}
}
