<?php

namespace App\Domains\ManagedServices\Services;

use App\Models\ManagedService;
use App\Models\SupplierAsset;

class ManagedServiceFinancialService
{
    public function summary(
        ManagedService $service
    ): array {
        $service->loadMissing([
            'assets',
            'costAllocations',
        ]);

        $allocations = $service
            ->costAllocations
            ->keyBy(
                'supplier_asset_id'
            );

        $costLines = $service
            ->assets
            ->map(
                function (
                    SupplierAsset $asset
                ) use ($allocations): array {
                    $allocation =
                        $allocations->get(
                            $asset->id
                        );

                    if ($allocation) {
                        return [
                            'asset' => $asset,

                            'cost' => round(
                                (float)
                                $allocation
                                    ->allocated_monthly_cost,
                                2
                            ),

                            'cost_source' => 'allocation',

                            'allocation_method' => $allocation
                                ->allocation_method,

                            'verified' => $allocation
                                ->verified,
                        ];
                    }

                    return [
                        'asset' => $asset,

                        'cost' => round(
                            (float)
                            $asset
                                ->observed_cost,
                            2
                        ),

                        'cost_source' => 'full_asset',

                        'allocation_method' => null,

                        'verified' => true,
                    ];
                }
            )
            ->values();

        $cost = round(
            (float) $costLines->sum(
                'cost'
            ),
            2
        );

        $revenue = round(
            (float) (
                $service
                    ->expected_monthly_revenue
                ?? 0
            ),
            2
        );

        $margin = round(
            $revenue - $cost,
            2
        );

        $marginPercent =
            $revenue > 0
                ? round(
                    ($margin / $revenue)
                    * 100,
                    2
                )
                : 0.0;

        return [
            'service' => $service,

            'monthly_cost' => $cost,

            'monthly_revenue' => $revenue,

            'monthly_margin' => $margin,

            'margin_percent' => $marginPercent,

            'asset_count' => $service->assets->count(),

            'cost_lines' => $costLines,
        ];
    }
}
