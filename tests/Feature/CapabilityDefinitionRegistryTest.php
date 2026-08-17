<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Contracts\CapabilityDefinitionContract;
use App\Domains\BusinessBrain\Capabilities\Definitions\ClientAdvocacyDefinition;
use App\Domains\BusinessBrain\Capabilities\Services\CapabilityDefinitionRegistry;
use Tests\TestCase;

class CapabilityDefinitionRegistryTest extends TestCase
{
    public function test_registry_contains_capability_definitions(): void
    {
        $definitions = app(
            CapabilityDefinitionRegistry::class
        )->definitions();

        $this->assertContains(
            ClientAdvocacyDefinition::class,
            $definitions
        );
    }

    public function test_registry_definitions_are_capability_definitions(): void
    {
        $definitions = app(
            CapabilityDefinitionRegistry::class
        )->definitions();

        foreach ($definitions as $definition) {
            $instance = new $definition;

            $this->assertInstanceOf(
                CapabilityDefinitionContract::class,
                $instance
            );
        }
    }
}
