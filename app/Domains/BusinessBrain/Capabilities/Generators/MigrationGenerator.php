<?php

namespace App\Domains\BusinessBrain\Capabilities\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MigrationGenerator
{
    public function __construct(
        protected Filesystem $files
    ) {}

    public function generate(string $name): void
    {
        $table = Str::snake(
            Str::pluralStudly($name)
        );

        $timestamp = now()
            ->format('Y_m_d_His');

        $path = database_path(
            "migrations/{$timestamp}_create_{$table}_table.php"
        );

        $this->files->put(
            $path,
            <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};

PHP
        );
    }
}
