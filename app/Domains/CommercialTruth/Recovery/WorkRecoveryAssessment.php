<?php

namespace App\Domains\CommercialTruth\Recovery;

class WorkRecoveryAssessment
{
    public function __construct(
        public string $state,
        public float $value,
        public int $confidence,
        public string $reason,
    ) {}
}
