<?php

namespace App\Domains\RevenueTruth;

class RevenueConfidenceService
{
    public function fromInfrastructure(
        string $confidence
    ): int {
        return match ($confidence) {
            'allocated' => 100,
            'high' => 95,
            'medium' => 75,
            'low' => 55,
            'no_billing_rule' => 30,
            'not_applicable' => 0,
            default => 40,
        };
    }
}
