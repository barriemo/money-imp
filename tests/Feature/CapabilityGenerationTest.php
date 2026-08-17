<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Generators\CapabilityGenerator;
use App\Domains\BusinessBrain\Capabilities\Services\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected array $generatedFiles = [
        'app/Models/TestGeneratedCapability.php',
        'database/factories/TestGeneratedCapabilityFactory.php',
        'app/Domains/Testing/Core/Services/TestGeneratedCapabilityService.php',
        'app/Domains/Testing/Core/Presenters/TestGeneratedCapabilityPresenter.php',
    ];

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $file) {
            $path = base_path($file);

            if (file_exists($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_capability_definition_generates_required_layers(): void
    {
        $capability = app(CapabilityRegistry::class)->register([
            'name' => 'TestGeneratedCapability',
            'domain' => 'Testing',
            'area' => 'Core',
            'owner' => 'TestingImp',
            'purpose' => 'Verify capability generation',
            'layers' => [
                'model',
                'migration',
                'factory',
                'service',
                'presenter',
                'test',
            ],
        ]);

        app(CapabilityGenerator::class)->generate(
            $capability
        );

        $this->assertFileExists(
            app_path('Models/TestGeneratedCapability.php')
        );

        $this->assertFileExists(
            database_path(
                'factories/TestGeneratedCapabilityFactory.php'
            )
        );

        $this->assertFileExists(
            app_path(
                'Domains/Testing/Core/Services/TestGeneratedCapabilityService.php'
            )
        );

        $this->assertFileExists(
            app_path(
                'Domains/Testing/Core/Presenters/TestGeneratedCapabilityPresenter.php'
            )
        );
    }
}
