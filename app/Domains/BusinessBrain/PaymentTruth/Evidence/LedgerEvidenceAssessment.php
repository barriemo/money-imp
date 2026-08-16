<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Evidence;

class LedgerEvidenceAssessment
{
    public function __construct(
        public string $status,

        public int $confidence,

        public array $observations,

        public array $possibleCauses,

        public string $recommendation
    ) {}
}
