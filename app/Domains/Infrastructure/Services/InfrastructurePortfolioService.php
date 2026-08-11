<?php

namespace App\Domains\Infrastructure\Services;

use App\Domains\Infrastructure\DTOs\InfrastructureBillingReconciliation;
use App\Models\SupplierAsset;
use Illuminate\Support\Collection;

class InfrastructurePortfolioService
{
    public function __construct(
        private InfrastructureBillingReconciliationService $billing
    ) {}

    public function reconcile(): array
    {
        $results = SupplierAsset::query()
            ->with([
                'client',
                'supplier',
            ])
            ->where('active', true)
            ->where('purpose', 'client')
            ->whereNotNull('client_id')
            ->get()
            ->map(
                fn (SupplierAsset $asset) => $this->billing->reconcile(
                    $asset
                )
            );

        return [
            'results' => $results,

            'asset_count' => $results->count(),

            'monthly_cost' => $this->sum(
                $results,
                'monthlyCost'
            ),

            'monthly_recovery' => $this->sum(
                $results,
                'monthlyRecovery'
            ),

            'monthly_margin' => round(
                $results->sum(
                    fn (
                        InfrastructureBillingReconciliation $item
                    ) => $item->monthlyMargin
                ),
                2
            ),

            'monthly_gap' => $this->sum(
                $results,
                'monthlyGap'
            ),

            'covered' => $this->statusCount(
                $results,
                'COVERED'
            ),

            'under_recovered' => $this->statusCount(
                $results,
                'UNDER_RECOVERED'
            ),

            'missing' => $this->statusCount(
                $results,
                'MISSING'
            ),

            'unknown' => $this->statusCount(
                $results,
                'UNKNOWN'
            ),
        ];
    }

    private function statusCount(
        Collection $results,
        string $status
    ): int {
        return $results
            ->where('status', $status)
            ->count();
    }

    private function sum(
        Collection $results,
        string $property
    ): float {
        return round(
            $results->sum(
                fn (
                    InfrastructureBillingReconciliation $item
                ) => $item->{$property}
            ),
            2
        );
    }
}
