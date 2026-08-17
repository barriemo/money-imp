<?php

namespace App\Domains\BusinessBrain\Capabilities\Generators;

use Illuminate\Filesystem\Filesystem;

class ModelGenerator
{
    public function __construct(
        protected Filesystem $files
    ) {}

    public function generate(string $name): void
    {
        $path = app_path(
            "Models/{$name}.php"
        );

        if ($this->files->exists($path)) {
            return;
        }

        $this->files->put(
            $path,
            <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {$name} extends Model
{
    use HasFactory;

    protected \$fillable = [];
}

PHP
        );
    }
}