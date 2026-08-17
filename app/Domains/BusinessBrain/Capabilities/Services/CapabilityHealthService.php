<?php

namespace App\Domains\BusinessBrain\Capabilities\Services;

use App\Models\CapabilityDefinition;

class CapabilityHealthService
{
    public function __construct(
        protected CapabilityEvidenceService $evidence
    ) {}

    public function calculate(
        CapabilityDefinition $capability
    ): int {
        $health = match ($capability->status) {
            'ready' => 50,
            'registered' => 25,
            default => 0,
        };

        $checks = $this->evidence->inspect(
            $capability
        );

        $health += count(
            array_filter($checks)
        ) * 10;

        return min(
            $health,
            100
        );
    }
}
