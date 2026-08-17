<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ImpCapabilityCommand extends Command
{
    protected $signature = 'imp:capability
        {name}
        {--domain=BusinessBrain}';

    protected $description = 'Generate a Money Imp capability scaffold';

    public function handle(): int
    {
        $name = $this->argument('name');

        $domain = $this->option('domain');

        $this->info(
            "Generating capability: {$name}"
        );

        $this->info(
            "Domain: {$domain}"
        );

        $this->createModel($name);

        return self::SUCCESS;
    }

    protected function createModel(string $name): void
    {
        $path = app_path(
            "Models/{$name}.php"
        );

        if ($this->files->exists($path)) {
            $this->warn(
                "{$name} model already exists"
            );

            return;
        }

        $this->files->put(
            $path,
            <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class {$name} extends Model
{
    use HasFactory;

    protected \$fillable = [];
}

PHP
        );

        $this->info(
            "Created model: {$name}"
        );
    }

    public function __construct(
        protected Filesystem $files
    ) {
        parent::__construct();
    }
}
