<?php

namespace App\Domains\BusinessBrain\Capabilities\Definitions;

use App\Domains\BusinessBrain\Capabilities\Contracts\CapabilityDefinitionContract;

class ClientAdvocacyDefinition implements CapabilityDefinitionContract
{
    public function definition(): array
    {
        return [
            'name' => 'ClientAdvocacy',
            'domain' => 'BusinessBrain',
            'area' => 'Client',
            'owner' => 'ReferralImp',
            'purpose' => 'Turn happy clients into introductions',
            'layers' => [
                'model',
                'migration',
                'factory',
                'service',
                'presenter',
                'test',
            ],
        ];
    }

    public function actions(): array
    {
        return [
            'Identify happy clients',
            'Request introductions',
            'Track referral outcomes',
        ];
    }
}
