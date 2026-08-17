<?php

namespace Tests\Feature;

use App\Models\CapabilityDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CapabilityBuildCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_command_moves_registered_capability_to_ready(): void
    {
        $capability = CapabilityDefinition::create([
            'name' => 'BuildTestCapability',
            'domain' => 'Testing',
            'area' => 'Core',
            'owner' => 'TestingImp',
            'purpose' => 'Verify capability build lifecycle',
            'layers' => [
                'model',
                'service',
            ],
            'status' => 'registered',
        ]);

        $this->artisan(
            'imp:build-capabilities'
        )
            ->expectsOutputToContain(
                'Building BuildTestCapability'
            )
            ->expectsOutputToContain(
                'Capabilities built.'
            )
            ->assertSuccessful();

        $this->assertSame(
            'ready',
            $capability->fresh()->status
        );

        $this->assertTrue(
            File::exists(
                app_path(
                    'Models/BuildTestCapability.php'
                )
            )
        );
    }
}
