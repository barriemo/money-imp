<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Definitions\ClientAdvocacyDefinition;
use App\Domains\BusinessBrain\Capabilities\Services\CapabilityInstaller;
use App\Models\CapabilityDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityInstallerTest extends TestCase
{
    use RefreshDatabase;

    public function test_installer_creates_client_advocacy_capability(): void
    {
        app(CapabilityInstaller::class)->install([
            ClientAdvocacyDefinition::class,
        ]);

        $capability = CapabilityDefinition::where(
            'name',
            'ClientAdvocacy'
        )
            ->first();

        $this->assertNotNull(
            $capability
        );

        $this->assertCount(
            3,
            $capability->actions
        );

        $this->assertTrue(
            $capability->actions
                ->contains('name', 'Identify happy clients')
        );
    }
}
