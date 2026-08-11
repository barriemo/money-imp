<?php

namespace App\Domains\Infrastructure\Billing;

use App\Models\Client;
use App\Models\InfrastructureBillingAllocation;
use App\Models\SupplierAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InfrastructureBillingAllocator
{
    public function __construct(
        private InfrastructureBillingMatcher $matcher
    ) {}

    public function allocate(
        Client $client
    ): array {
        $line = $this->matcher
            ->latestHostingLine($client);

        if (! $line) {
            return [
                'status' => 'NO_BILLING',
                'assets' => 0,
                'available_recovery' => 0.0,
                'allocated_recovery' => 0.0,
            ];
        }

        $assets = $this->assetsFor(
            $client
        );

        if ($assets->isEmpty()) {
            return [
                'status' => 'NO_ASSETS',
                'assets' => 0,
                'available_recovery' => (float) $line->unit_price,
                'allocated_recovery' => 0.0,
            ];
        }

        $totalCost = (float) $assets->sum(
            fn (SupplierAsset $asset) => (float) $asset->observed_cost
        );

        if ($totalCost <= 0) {
            return [
                'status' => 'NO_COST',
                'assets' => $assets->count(),
                'available_recovery' => (float) $line->unit_price,
                'allocated_recovery' => 0.0,
            ];
        }

        $available = round(
            (float) $line->unit_price,
            2
        );

        DB::transaction(
            function () use (
                $assets,
                $totalCost,
                $available,
                $line
            ): void {
                InfrastructureBillingAllocation::query()
                    ->where(
                        'accounting_invoice_item_id',
                        $line->id
                    )
                    ->delete();

                $remaining = $available;

                $assets
                    ->values()
                    ->each(
                        function (
                            SupplierAsset $asset,
                            int $index
                        ) use (
                            $assets,
                            $totalCost,
                            $available,
                            $line,
                            &$remaining
                        ): void {
                            $isLast =
                                $index
                                === $assets->count() - 1;

                            $amount = $isLast
                                ? $remaining
                                : round(
                                    $available
                                    * (
                                        (float)
                                        $asset->observed_cost
                                        / $totalCost
                                    ),
                                    2
                                );

                            $amount = max(
                                0,
                                min(
                                    $remaining,
                                    $amount
                                )
                            );

                            InfrastructureBillingAllocation::create([
                                'supplier_asset_id' => $asset->id,

                                'accounting_invoice_item_id' => $line->id,

                                'allocated_amount' => $amount,

                                'confidence' => 100,

                                'source' => 'proportional_cost',

                                'verified' => false,

                                'metadata' => [
                                    'invoice_number' => $line->invoice_number,

                                    'invoice_date' => $line->invoice_date,

                                    'description' => $line->description,
                                ],
                            ]);

                            $remaining = round(
                                $remaining - $amount,
                                2
                            );
                        }
                    );
            }
        );

        $allocated = (float)
            InfrastructureBillingAllocation::query()
                ->where(
                    'accounting_invoice_item_id',
                    $line->id
                )
                ->sum('allocated_amount');

        return [
            'status' => 'ALLOCATED',

            'assets' => $assets->count(),

            'available_recovery' => $available,

            'allocated_recovery' => round(
                $allocated,
                2
            ),
        ];
    }

    private function assetsFor(
        Client $client
    ): Collection {
        return SupplierAsset::query()
            ->where(
                'client_id',
                $client->id
            )
            ->where(
                'purpose',
                'client'
            )
            ->where(
                'billable',
                true
            )
            ->where(
                'active',
                true
            )
            ->whereIn(
                'asset_type',
                [
                    'hosting_server',
                    'hosting_addon',
                    'storage',
                ]
            )
            ->orderBy('id')
            ->get();
    }
}
