<?php

namespace App\Domains\Suppliers\Assets;

use App\Models\BankTransaction;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Support\Str;

class SupplierAssetClassifier
{
    public function __construct(
        private SupplierAssetDetector $detector
    ) {}

    public function classify(
        SupplierProfile $supplier,
        BankTransaction $transaction
    ): int {
        $count = 0;

        foreach (
            $this->detector->detect(
                $transaction
            ) as $detected
        ) {
            if (
                $this->isSupplierIdentity(
                    $supplier,
                    $detected
                )
            ) {
                continue;
            }
            $asset = SupplierAsset::firstOrCreate(
                [
                    'supplier_profile_id' => $supplier->id,

                    'asset_type' => $detected['type'],

                    'asset_key' => $detected['key'],
                ],
                [
                    'name' => $detected['name'],

                    'confidence' => $detected['confidence'],

                    'first_seen_at' => $transaction->transaction_date,

                    'last_seen_at' => $transaction->transaction_date,

                    'active' => true,
                ]
            );

            $asset->update([
                'last_seen_at' => max(
                    $asset->last_seen_at
                        ?->toDateString()
                        ?? '0000-00-00',
                    $transaction
                        ->transaction_date
                        ?->toDateString()
                        ?? '0000-00-00'
                ),

                'observed_cost' => round(
                    (float) $asset
                        ->observed_cost
                    + abs(
                        (float)
                        $transaction->amount
                    ),
                    2
                ),
            ]);

            $count++;
        }

        return $count;
    }

    private function isSupplierIdentity(
        SupplierProfile $supplier,
        array $detected
    ): bool {
        if ($detected['type'] !== 'domain') {
            return false;
        }

        $asset = $this->normalise(
            $detected['key']
        );

        $supplierKey = $this->normalise(
            $supplier->supplier_key
        );

        $supplierName = $this->normalise(
            $supplier->supplier_name
        );

        return $asset === $supplierKey
            || $asset === $supplierName;
    }

    private function normalise(
        ?string $value
    ): string {
        return Str::of($value ?? '')
            ->lower()
            ->replaceMatches(
                '/[^a-z0-9]+/',
                ' '
            )
            ->squish()
            ->value();
    }
}
