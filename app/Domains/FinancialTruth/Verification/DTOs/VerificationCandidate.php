<?php

namespace App\Domains\FinancialTruth\Verification\DTOs;

class VerificationCandidate
{
    public function __construct(
        public string $key,

        public string $type,

        public string $subject,

        public ?float $amount,

        public string $source,

        public int $confidence,

        public int $priority,

        public string $reason,

        public string $recommendedAction
    ) {}
}
