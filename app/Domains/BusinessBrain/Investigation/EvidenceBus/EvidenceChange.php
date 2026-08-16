<?php

namespace App\Domains\BusinessBrain\Investigation\EvidenceBus;

class EvidenceChange
{
    public function __construct(
        public string $domain,

        public string $type,

        public ?string $subjectType = null,

        public ?string $subjectId = null,

        public array $metadata = []
    ) {}
}
