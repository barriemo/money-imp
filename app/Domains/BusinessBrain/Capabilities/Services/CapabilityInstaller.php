<?php

namespace App\Domains\BusinessBrain\Capabilities\Services;

use App\Domains\BusinessBrain\Capabilities\Contracts\CapabilityDefinitionContract;

class CapabilityInstaller
{
    public function __construct(
        protected CapabilityRegistry $registry
    ) {}

    public function install(
        array $definitions
    ): void {
        foreach ($definitions as $definition) {
            $this->installDefinition(
                new $definition
            );
        }
    }

    protected function installDefinition(
        CapabilityDefinitionContract $definition
    ): void {
        $capability = $this->registry->register(
            $definition->definition()
        );

        foreach ($definition->actions() as $action) {
            $capability->actions()->firstOrCreate([
                'name' => $action,
            ]);
        }
    }
}
