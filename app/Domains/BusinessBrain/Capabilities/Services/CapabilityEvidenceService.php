<?php

namespace App\Domains\BusinessBrain\Capabilities\Services;

use App\Models\CapabilityDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CapabilityEvidenceService
{
    public function inspect(
        CapabilityDefinition $capability
    ): array {
        $name = $capability->name;

        $table = Str::snake(
            Str::pluralStudly($name)
        );

        return [
            'model' => File::exists(
                app_path("Models/{$name}.php")
            ),

            'migration' => $this->migrationExists(
                $table
            ),

            'factory' => File::exists(
                database_path(
                    "factories/{$name}Factory.php"
                )
            ),

            'service' => File::exists(
                app_path(
                    "Domains/{$capability->domain}/{$capability->area}/Services/{$name}Service.php"
                )
            ),

            'presenter' => File::exists(
                app_path(
                    "Domains/{$capability->domain}/{$capability->area}/Presenters/{$name}Presenter.php"
                )
            ),

            'test' => File::exists(
                base_path(
                    "tests/Feature/{$name}Test.php"
                )
            ),
        ];
    }

    protected function migrationExists(
        string $table
    ): bool {
        return count(
            File::glob(
                database_path(
                    "migrations/*_create_{$table}_table.php"
                )
            )
        ) > 0;
    }
}
