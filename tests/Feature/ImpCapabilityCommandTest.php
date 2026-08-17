<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Services\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImpCapabilityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_generator_creates_model(): void
    {
        $path = app_path(
            'Models/TestGeneratedCapability.php'
        );

        if (File::exists($path)) {
            File::delete($path);
        }

        app(CapabilityRegistry::class)->register([
            'name' => 'TestGeneratedCapability',
            'domain' => 'Testing',
            'area' => 'Core',
            'owner' => 'TestingImp',
            'purpose' => 'Verify capability generation',
            'layers' => [
                'model',
            ],
        ]);

        $this->artisan(
            'imp:capability TestGeneratedCapability'
        )
            ->expectsOutputToContain(
                'Capability generated'
            )
            ->assertSuccessful();

        $this->assertTrue(
            File::exists($path)
        );

        File::delete($path);
    }
}
