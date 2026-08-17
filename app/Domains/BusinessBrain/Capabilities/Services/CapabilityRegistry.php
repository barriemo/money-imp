<?php

namespace App\Domains\BusinessBrain\Capabilities\Services;

use App\Models\CapabilityDefinition;

class CapabilityRegistry
{
    public function register(
        array $definition
    ): CapabilityDefinition {
        $definition['status'] ??= 'registered';

        return CapabilityDefinition::updateOrCreate(
            [
                'name' => $definition['name'],
            ],
            $definition
        );
    }

    public function find(
        string $name
    ): ?CapabilityDefinition {
        return CapabilityDefinition::where(
            'name',
            $name
        )->first();
    }
}
