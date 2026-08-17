<?php

namespace App\Domains\BusinessBrain\Capabilities\Generators;

use Illuminate\Filesystem\Filesystem;

class ServiceGenerator
{
    public function __construct(
        protected Filesystem $files
    ) {}

    public function generate(
        string $name,
        string $domain = 'BusinessBrain',
        string $area = 'Core'
    ): void {
        $path = app_path(
            "Domains/{$domain}/{$area}/Services/{$name}Service.php"
        );

        if ($this->files->exists($path)) {
            return;
        }

        $this->files->ensureDirectoryExists(
            dirname($path)
        );

        $this->files->put(
            $path,
            <<<PHP
<?php

namespace App\Domains\\{$domain}\\{$area}\\Services;

class {$name}Service
{
    public function handle(): array
    {
        return [];
    }
}

PHP
        );
    }
}
