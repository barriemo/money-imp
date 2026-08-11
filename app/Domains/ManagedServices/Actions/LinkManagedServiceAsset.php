<?php

namespace App\Domains\ManagedServices\Actions;

use App\Models\ManagedService;
use App\Models\ManagedServiceAsset;
use App\Models\SupplierAsset;

class LinkManagedServiceAsset
{
    public function execute(
        ManagedService $service,
        SupplierAsset $asset,
        string $role = 'dependency',
        int $confidence = 100,
        bool $verified = false,
        string $source = 'manual',
        array $metadata = []
    ): ManagedServiceAsset {
        return ManagedServiceAsset::updateOrCreate(
            [
                'managed_service_id' => $service->id,

                'supplier_asset_id' => $asset->id,
            ],
            [
                'role' => $role,

                'confidence' => max(
                    0,
                    min(
                        100,
                        $confidence
                    )
                ),

                'verified' => $verified,

                'source' => $source,

                'metadata' => $metadata,
            ]
        );
    }
}
