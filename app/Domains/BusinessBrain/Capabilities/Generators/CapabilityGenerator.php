<?php

namespace App\Domains\BusinessBrain\Capabilities\Generators;

use App\Models\CapabilityDefinition;

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
        CapabilityDefinition $capability
    ): void {
        $name = $capability->name;

        $domain = $capability->domain;

        $area = $capability->area;

        foreach ($capability->layers as $layer) {
            match ($layer) {
                'model' => $this->models->generate($name),
                'migration' => $this->migrations->generate($name),
                'factory' => $this->factories->generate($name),
                'test' => $this->tests->generate($name),
                'service' => $this->services->generate(
                    $name,
                    $domain,
                    $area
                ),
                'presenter' => $this->presenters->generate(
                    $name,
                    $domain,
                    $area
                ),
                default => null,
            };
        }
    }
}
