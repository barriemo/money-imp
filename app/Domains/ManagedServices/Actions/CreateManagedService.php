<?php

namespace App\Domains\ManagedServices\Actions;

use App\Models\Client;
use App\Models\ManagedService;

class CreateManagedService
{
    public function execute(
        Client $client,
        string $type,
        string $name,
        bool $billable = true,
        ?float $expectedMonthlyRevenue = null,
        string $source = 'manual',
        int $confidence = 100,
        array $metadata = []
    ): ManagedService {
        return ManagedService::updateOrCreate(
            [
                'client_id' => $client->id,

                'service_type' => $type,

                'name' => $name,
            ],
            [
                'status' => 'active',

                'billable' => $billable,

                'expected_monthly_revenue' => $expectedMonthlyRevenue,

                'source' => $source,

                'confidence' => max(
                    0,
                    min(
                        100,
                        $confidence
                    )
                ),

                'metadata' => $metadata,
            ]
        );
    }
}
