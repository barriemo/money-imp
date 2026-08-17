<?php

namespace App\Domains\BusinessBrain\Capabilities\Generators;

class CapabilityGenerator
{
    public function __construct(
        protected ModelGenerator $models,
        protected MigrationGenerator $migrations,
        protected FactoryGenerator $factories,
        protected TestGenerator $tests,
        protected ServiceGenerator $services,
        protected PresenterGenerator $presenters
    ) {}

    public function generate(
        string $name,
        string $domain
    ): void {
        $area = $this->resolveArea($name);

        $this->models->generate($name);

        $this->migrations->generate($name);

        $this->factories->generate($name);

        $this->tests->generate($name);

        $this->services->generate(
            $name,
            $domain,
            $area
        );

        $this->presenters->generate(
            $name,
            $domain,
            $area
        );
    }

    protected function resolveArea(string $name): string
    {
        if (str_contains($name, 'ClientRequest')) {
            return 'Client';
        }

        if (str_contains($name, 'ProjectAction')) {
            return 'Project';
        }

        return 'Core';
    }
}
