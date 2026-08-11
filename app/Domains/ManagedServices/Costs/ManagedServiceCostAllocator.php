<?php

namespace App\Domains\ManagedServices\Costs;

use App\Models\ManagedService;
use App\Models\ManagedServiceCostAllocation;
use App\Models\SupplierAsset;
use InvalidArgumentException;

class ManagedServiceCostAllocator
{
    public function allocatePercent(
        ManagedService $service,
        SupplierAsset $asset,
        float $percent,
        bool $verified = false,
        string $source = 'manual'
    ): ManagedServiceCostAllocation {
        if (
            $percent < 0
            || $percent > 100
        ) {
            throw new InvalidArgumentException(
                'Allocation percent must be between 0 and 100.'
            );
        }

        $cost = round(
            (float) $asset->observed_cost
            * ($percent / 100),
            2
        );

        return $this->store(
            service: $service,
            asset: $asset,
            cost: $cost,
            percent: $percent,
            method: 'percentage',
            verified: $verified,
            source: $source
        );
    }

    public function allocateAmount(
        ManagedService $service,
        SupplierAsset $asset,
        float $amount,
        bool $verified = false,
        string $source = 'manual'
    ): ManagedServiceCostAllocation {
        if ($amount < 0) {
            throw new InvalidArgumentException(
                'Allocated cost cannot be negative.'
            );
        }

        $assetCost =
            (float) $asset->observed_cost;

        $percent =
            $assetCost > 0
                ? round(
                    ($amount / $assetCost)
                    * 100,
                    4
                )
                : null;

        return $this->store(
            service: $service,
            asset: $asset,
            cost: round(
                $amount,
                2
            ),
            percent: $percent,
            method: 'amount',
            verified: $verified,
            source: $source
        );
    }

    private function store(
        ManagedService $service,
        SupplierAsset $asset,
        float $cost,
        ?float $percent,
        string $method,
        bool $verified,
        string $source
    ): ManagedServiceCostAllocation {
        return ManagedServiceCostAllocation::updateOrCreate(
            [
                'managed_service_id' => $service->id,

                'supplier_asset_id' => $asset->id,
            ],
            [
                'allocation_method' => $method,

                'allocated_monthly_cost' => $cost,

                'allocation_percent' => $percent,

                'confidence' => $verified
                        ? 100
                        : 75,

                'verified' => $verified,

                'source' => $source,
            ]
        );
    }
}
