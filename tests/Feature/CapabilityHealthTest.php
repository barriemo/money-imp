<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Services\CapabilityHealthService;
use App\Models\CapabilityDefinition;
use Tests\TestCase;

class CapabilityHealthTest extends TestCase
{
    public function test_ready_capability_with_all_layers_has_full_health(): void
    {
        $capability = new CapabilityDefinition([
            'status' => 'ready',
            'layers' => [
                'model',
                'migration',
                'factory',
                'service',
                'presenter',
                'test',
            ],
        ]);

        $health = app(CapabilityHealthService::class)->calculate(
            $capability
        );

        $this->assertSame(
            100,
            $health
        );
    }

    public function test_registered_capability_has_lower_health(): void
    {
        $capability = new CapabilityDefinition([
            'status' => 'registered',
            'layers' => [
                'service',
            ],
        ]);

        $health = app(CapabilityHealthService::class)->calculate(
            $capability
        );

        $this->assertSame(
            35,
            $health
        );
    }
}
