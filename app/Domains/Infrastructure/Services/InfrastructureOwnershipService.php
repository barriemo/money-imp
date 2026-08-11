<?php

namespace App\Domains\Infrastructure\Services;

use App\Models\SupplierAsset;
use Illuminate\Support\Collection;

class InfrastructureOwnershipService
{
    public function ownedAssets(): Collection
    {
        return SupplierAsset::query()
            ->with([
                'supplier',
                'client',
                'outgoingRelationships.toAsset',
                'incomingRelationships.fromAsset',
            ])
            ->whereNotNull('client_id')
            ->orderByDesc('observed_cost')
            ->get()
            ->map(
                function (SupplierAsset $asset): array {
                    return [
                        'asset' => $asset,

                        'client' => $asset->client,

                        'supplier' => $asset->supplier,

                        'monthly_cost' => (float) $asset->observed_cost,

                        'billable' => (bool) $asset->billable,

                        'expected_charge' => (float) (
                            $asset->expected_charge
                            ?? 0
                        ),

                        'outgoing' => $asset->outgoingRelationships,

                        'incoming' => $asset->incomingRelationships,
                    ];
                }
            );
    }
}
