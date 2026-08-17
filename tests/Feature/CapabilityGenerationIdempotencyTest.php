<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Generators\CapabilityGenerator;
use App\Domains\BusinessBrain\Capabilities\Services\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CapabilityGenerationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(
            app_path('Domains/Testing')
        );

        File::delete(
            app_path('Models/TestIdempotentCapability.php')
        );

        File::delete(
            database_path(
                'factories/TestIdempotentCapabilityFactory.php'
            )
        );

        parent::tearDown();
    }

    public function test_generating_same_capability_twice_does_not_duplicate_files(): void
    {
        $capability = app(CapabilityRegistry::class)->register([
            'name' => 'TestIdempotentCapability',
            'domain' => 'Testing',
            'area' => 'Core',
            'owner' => 'TestingImp',
            'purpose' => 'Verify safe repeated generation',
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

        app(CapabilityGenerator::class)->generate(
            $capability
        );

        $migrations = glob(
            database_path(
                'migrations/*test_idempotent_capabilities_table.php'
            )
        );

        $this->assertCount(
            1,
            $migrations
        );

        $this->assertFileExists(
            app_path('Models/TestIdempotentCapability.php')
        );
    }
}
