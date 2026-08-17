<?php

namespace App\Domains\BusinessBrain\Capabilities\Generators;

use Illuminate\Filesystem\Filesystem;

class PresenterGenerator
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
            "Domains/{$domain}/{$area}/Presenters/{$name}Presenter.php"
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

namespace App\Domains\\{$domain}\\{$area}\\Presenters;

class {$name}Presenter
{
    public function present(
        mixed \$data
    ): array {
        return [
            'data' => \$data,
        ];
    }
}

PHP
        );
    }
}
