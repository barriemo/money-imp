<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Services\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CapabilityOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_requires_valid_owner(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        app(CapabilityRegistry::class)->register([
            'name' => 'InvalidOwnerCapability',
            'domain' => 'BusinessBrain',
            'area' => 'Core',
            'owner' => 'RandomPerson',
            'purpose' => 'Test invalid ownership',
            'layers' => [
                'service',
            ],
        ]);
    }

    public function test_capability_accepts_known_owner(): void
    {
        $capability = app(CapabilityRegistry::class)->register([
            'name' => 'OwnedCapability',
            'domain' => 'BusinessBrain',
            'area' => 'Core',
            'owner' => 'BusinessBrain',
            'purpose' => 'Test valid ownership',
            'layers' => [
                'service',
            ],
        ]);

        $this->assertSame(
            'BusinessBrain',
            $capability->owner
        );
    }
}
