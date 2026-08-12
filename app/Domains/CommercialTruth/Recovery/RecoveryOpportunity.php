<?php

namespace App\Domains\CommercialTruth\Recovery;

class RecoveryOpportunity
{
    public function __construct(
        public string $clientId,
        public string $workLogId,
        public float $value,
        public int $confidence,
        public string $reason,
    ) {}
}
