<?php

namespace App\Domains\BusinessBrain\Capabilities\Generators;

class CapabilityGenerator
{
    public function __construct(
        protected ModelGenerator $models,
        protected MigrationGenerator $migrations,
        protected FactoryGenerator $factories
    ) {}

    public function generate(
        string $name,
        string $domain
    ): void {
        $this->models->generate($name);

        $this->migrations->generate($name);

        $this->factories->generate($name);
    }
}
