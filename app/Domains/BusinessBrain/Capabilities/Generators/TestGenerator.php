<?php

namespace App\Domains\BusinessBrain\Capabilities\Generators;

use Illuminate\Filesystem\Filesystem;

class TestGenerator
{
    public function __construct(
        protected Filesystem $files
    ) {}

    public function generate(string $name): void
    {
        $path = base_path(
            "tests/Feature/{$name}Test.php"
        );

        if ($this->files->exists($path)) {
            return;
        }

        $this->files->put(
            $path,
            <<<PHP
<?php

namespace Tests\Feature;

use Tests\TestCase;

class {$name}Test extends TestCase
{
    public function test_{$this->testName($name)}(): void
    {
        \$this->assertTrue(true);
    }
}

PHP
        );
    }

    protected function testName(string $name): string
    {
        return strtolower(
            preg_replace(
                '/(?<!^)[A-Z]/',
                '_$0',
                $name
            )
        );
    }
}
