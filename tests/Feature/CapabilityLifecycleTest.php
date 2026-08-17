<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Generators\CapabilityGenerator;
use App\Domains\BusinessBrain\Capabilities\Services\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_moves_from_registered_to_ready_after_generation(): void
    {
        $capability = app(CapabilityRegistry::class)->register([
            'name' => 'TestLifecycleCapability',
            'domain' => 'Testing',
            'area' => 'Core',
            'owner' => 'TestingImp',
            'purpose' => 'Verify lifecycle state',
            'layers' => [
                'service',
            ],
        ]);

        $this->assertSame(
            'registered',
            $capability->status
        );

        app(CapabilityGenerator::class)->generate(
            $capability
        );

        $this->assertSame(
            'ready',
            $capability->fresh()->status
        );
    }
}
