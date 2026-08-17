<?php

namespace App\Domains\BusinessBrain\Capabilities\Services;

use App\Models\CapabilityDefinition;
use InvalidArgumentException;

class CapabilityRegistry
{
    public function register(
        array $definition
    ): CapabilityDefinition {
        $this->validateOwner(
            $definition['owner']
        );

        return CapabilityDefinition::updateOrCreate(
            [
                'name' => $definition['name'],
            ],
            [
                ...$definition,
                'status' => 'registered',
            ]
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

    protected function validateOwner(
        string $owner
    ): void {
        if (! in_array(
            $owner,
            config('imp.capability_owners'),
            true
        )) {
            throw new InvalidArgumentException(
                "Unknown capability owner: {$owner}"
            );
        }
    }
}
