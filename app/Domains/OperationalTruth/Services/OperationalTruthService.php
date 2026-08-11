<?php

namespace App\Domains\OperationalTruth\Services;

use App\Domains\Infrastructure\Services\ClientServiceGapService;
use App\Domains\Infrastructure\Services\InfrastructurePortfolioService;
use App\Domains\ManagedServices\Services\ManagedServiceFinancialService;
use App\Models\ManagedService;
use App\Models\ManagedServiceCostAllocation;
use App\Models\SupplierAsset;

class OperationalTruthService
{
    public function __construct(
        private InfrastructurePortfolioService $infrastructure,
        private ClientServiceGapService $serviceGaps,
        private ManagedServiceFinancialService $managedFinancials
    ) {}

    public function summary(): array
    {
        $infrastructure =
            $this->infrastructure->reconcile();

        $services = ManagedService::query()
            ->with([
                'client',
                'assets',
                'costAllocations',
            ])
            ->where(
                'status',
                'active'
            )
            ->get();

        $serviceFinancials =
            $services->map(
                fn (ManagedService $service) => $this->managedFinancials
                    ->summary($service)
            );

        $managedRevenue = round(
            (float) $serviceFinancials->sum(
                'monthly_revenue'
            ),
            2
        );

        $managedCost = round(
            (float) $serviceFinancials->sum(
                'monthly_cost'
            ),
            2
        );

        $managedMargin = round(
            $managedRevenue - $managedCost,
            2
        );

        $serviceGaps =
            $this->serviceGaps->all();

        $unallocatedAssets =
            SupplierAsset::query()
                ->where(
                    'active',
                    true
                )
                ->whereNull(
                    'purpose'
                )
                ->count();

        $unknownCostAssets =
            SupplierAsset::query()
                ->where(
                    'active',
                    true
                )
                ->where(function ($query): void {
                    $query
                        ->whereNull(
                            'observed_cost'
                        )
                        ->orWhere(
                            'observed_cost',
                            '<=',
                            0
                        );
                })
                ->count();

        $unverifiedCostAllocations =
            ManagedServiceCostAllocation::query()
                ->where(
                    'verified',
                    false
                )
                ->count();

        return [
            'managed_services' => [
                'count' => $services->count(),

                'monthly_revenue' => $managedRevenue,

                'monthly_cost' => $managedCost,

                'monthly_margin' => $managedMargin,
            ],

            'infrastructure' => [
                'asset_count' => $infrastructure[
                        'asset_count'
                    ],

                'monthly_cost' => $infrastructure[
                        'monthly_cost'
                    ],

                'monthly_recovery' => $infrastructure[
                        'monthly_recovery'
                    ],

                'monthly_margin' => $infrastructure[
                        'monthly_margin'
                    ],

                'monthly_gap' => $infrastructure[
                        'monthly_gap'
                    ],
            ],

            'gaps' => [
                'client_service_gaps' => $serviceGaps->count(),

                'unallocated_assets' => $unallocatedAssets,

                'unknown_cost_assets' => $unknownCostAssets,

                'unverified_cost_allocations' => $unverifiedCostAllocations,
            ],
        ];
    }
}
