<?php

namespace App\Domains\BusinessBrain\Capabilities\Services;

use App\Domains\BusinessBrain\Capabilities\Definitions\ClientAdvocacyDefinition;

class CapabilityDefinitionRegistry
{
    public function definitions(): array
    {
        return [
            ClientAdvocacyDefinition::class,
        ];
    }
}
