<?php

namespace App\Domains\BusinessBrain;

use App\Domains\Evidence\EvidenceItem;

class BusinessObservation
{
    public function __construct(
        public string $type,
        public string $summary,
        public int $confidence,
        public array $data = [],
        public array $evidence = []
    ) {}

    public function addEvidence(
        EvidenceItem $evidence
    ): self {
        $this->evidence[] =
            $evidence;

        return $this;
    }
}
