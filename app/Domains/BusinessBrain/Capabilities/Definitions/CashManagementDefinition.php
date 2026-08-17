<?php

namespace App\Domains\BusinessBrain\Capabilities\Definitions;

use App\Domains\BusinessBrain\Capabilities\Contracts\CapabilityDefinitionContract;

class CashManagementDefinition implements CapabilityDefinitionContract
{
    public function definition(): array
    {
        return [
            'name' => 'CashManagement',
            'domain' => 'BusinessBrain',
            'area' => 'Finance',
            'owner' => 'CFOImp',
            'purpose' => 'Identify and prioritise actions that improve cash control and financial visibility',
            'layers' => [
                'service',
                'truth',
                'reporting',
            ],
            'status' => 'registered',
        ];
    }

    public function actions(): array
    {
        return [
            'Identify cash risks',
            'Review overdue invoices',
            'Highlight unknown liabilities',
        ];
    }
}
