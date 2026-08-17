<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Services\CapabilityHealthService;
use App\Models\CapabilityDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_capability_with_all_layers_has_full_health(): void
    {
        $capability = CapabilityDefinition::create([
            'name' => 'ClientAdvocacy',
            'domain' => 'BusinessBrain',
            'area' => 'Client',
            'owner' => 'ReferralImp',
            'purpose' => 'Turn happy clients into introductions',
            'layers' => [
                'model',
                'migration',
                'factory',
                'service',
                'presenter',
                'test',
            ],
            'status' => 'ready',
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
            'name' => 'MissingCapability',
            'status' => 'registered',
            'domain' => 'BusinessBrain',
            'area' => 'Client',
            'layers' => [
                'service',
            ],
        ]);

        $health = app(CapabilityHealthService::class)->calculate(
            $capability
        );

        $this->assertLessThan(
            100,
            $health
        );
    }
}
