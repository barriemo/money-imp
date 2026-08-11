<?php

namespace App\Domains\Infrastructure\Services;

use App\Models\SupplierAsset;
use Illuminate\Support\Collection;

class InfrastructureRecoveryService
{
    public function summary(): array
    {
        $assets = SupplierAsset::query()
            ->with('client')
            ->where('active', true)
            ->get();

        $clientAssets = $assets
            ->where('purpose', 'client');

        $cost = (float) $clientAssets->sum(
            fn (SupplierAsset $asset) => (float) $asset->observed_cost
        );

        $expected = (float) $clientAssets
            ->where('billable', true)
            ->sum(
                fn (SupplierAsset $asset) => (float) (
                    $asset->expected_charge
                    ?? 0
                )
            );

        return [
            'client_cost' => round($cost, 2),
            'expected_recovery' => round($expected, 2),
            'recovery_gap' => round(
                max(0, $cost - $expected),
                2
            ),
            'missing_charge_assets' => $clientAssets
                ->where('billable', true)
                ->filter(
                    fn (SupplierAsset $asset) => (float) (
                        $asset->expected_charge
                        ?? 0
                    ) <= 0
                )
                ->values(),
        ];
    }

    public function missingCharges(): Collection
    {
        return $this->summary()[
            'missing_charge_assets'
        ];
    }
}
