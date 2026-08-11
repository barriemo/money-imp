<?php

namespace App\Domains\ManagedServices\Services;

use App\Models\ManagedService;
use App\Models\SupplierAsset;

class ManagedServiceFinancialService
{
    public function summary(
        ManagedService $service
    ): array {
        $service->loadMissing(
            'assets'
        );

        $cost = round(
            (float) $service->assets->sum(
                fn (SupplierAsset $asset) => (float)
                    $asset->observed_cost
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
        ];
    }
}
