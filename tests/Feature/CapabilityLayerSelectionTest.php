<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Generators\CapabilityGenerator;
use App\Domains\BusinessBrain\Capabilities\Services\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CapabilityLayerSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(
            app_path('Models/TestServiceOnlyCapability.php')
        );

        File::delete(
            app_path(
                'Domains/Testing/Core/Services/TestServiceOnlyCapabilityService.php'
            )
        );

        File::deleteDirectory(
            app_path('Domains/Testing')
        );

        parent::tearDown();
    }

    public function test_capability_only_generates_requested_layers(): void
    {
        $capability = app(CapabilityRegistry::class)->register([
            'name' => 'TestServiceOnlyCapability',
            'domain' => 'Testing',
            'area' => 'Core',
            'owner' => 'TestingImp',
            'purpose' => 'Verify layer selection',
            'layers' => [
                'service',
            ],
        ]);

        app(CapabilityGenerator::class)->generate(
            $capability
        );

        $this->assertFileExists(
            app_path(
                'Domains/Testing/Core/Services/TestServiceOnlyCapabilityService.php'
            )
        );

        $this->assertFileDoesNotExist(
            app_path(
                'Models/TestServiceOnlyCapability.php'
            )
        );

        $this->assertFileDoesNotExist(
            database_path(
                'factories/TestServiceOnlyCapabilityFactory.php'
            )
        );
    }
}
