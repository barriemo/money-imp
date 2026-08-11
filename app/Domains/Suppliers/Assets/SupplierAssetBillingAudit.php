<?php

namespace App\Domains\Suppliers\Assets;

use App\Models\SupplierAsset;
use Illuminate\Support\Collection;

class SupplierAssetBillingAudit
{
    public function needsReview(): Collection
    {
        return SupplierAsset::query()
            ->where('active', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('purpose')
                    ->orWhere(function ($query): void {
                        $query
                            ->where('billable', true)
                            ->whereNull(
                                'expected_charge'
                            );
                    });
            })
            ->orderByDesc('observed_cost')
            ->get();
    }

    public function potentialAnnualRecovery(): float
    {
        return (float) SupplierAsset::query()
            ->where('active', true)
            ->where('billable', true)
            ->whereNotNull('expected_charge')
            ->sum('expected_charge');
    }
}
