<?php

namespace App\Domains\BusinessBrain\Capabilities\Generators;

use Illuminate\Filesystem\Filesystem;

class FactoryGenerator
{
    public function __construct(
        protected Filesystem $files
    ) {}

    public function generate(string $name): void
    {
        $path = database_path(
            "factories/{$name}Factory.php"
        );

        if ($this->files->exists($path)) {
            return;
        }

        $this->files->put(
            $path,
            <<<PHP
<?php

namespace Database\Factories;

use App\Models\\{$name};
use Illuminate\Database\Eloquent\Factories\Factory;

class {$name}Factory extends Factory
{
    protected \$model = {$name}::class;

    public function definition(): array
    {
        return [];
    }
}

PHP
        );
    }
}
