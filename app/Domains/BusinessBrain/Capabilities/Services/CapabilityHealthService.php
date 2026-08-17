<?php

namespace App\Domains\BusinessBrain\Capabilities\Services;

use App\Models\CapabilityDefinition;

class CapabilityHealthService
{
    public function calculate(
        CapabilityDefinition $capability
    ): int {
        $health = match ($capability->status) {
            'ready' => 50,
            'registered' => 25,
            default => 0,
        };

        $health += count($capability->layers) * 10;

        return min(
            $health,
            100
        );
    }
}
