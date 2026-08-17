<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Generators\MigrationGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MigrationGeneratorIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_generator_does_not_create_duplicate_migrations(): void
    {
        $generator = app(MigrationGenerator::class);

        $generator->generate(
            'TestMigrationCapability'
        );

        $generator->generate(
            'TestMigrationCapability'
        );

        $migrations = glob(
            database_path(
                'migrations/*create_test_migration_capabilities_table.php'
            )
        );

        $this->assertCount(
            1,
            $migrations
        );

        foreach ($migrations as $migration) {
            File::delete($migration);
        }
    }
}
