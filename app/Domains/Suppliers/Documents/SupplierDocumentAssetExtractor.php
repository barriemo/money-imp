<?php

namespace App\Domains\Suppliers\Documents;

use App\Models\ImportBatch;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use RuntimeException;

class SupplierDocumentAssetExtractor
{
    public function __construct(
        private readonly Services\SupplierDocumentDetectionService $documents,
    ) {}

    public function extract(
        ImportBatch $batch
    ): int {
        $detected = $this->documents->detect($batch);

        return $this->extractDetected(
            $batch,
            $detected
        );
    }

    public function extractDetected(
        ImportBatch $batch,
        array $detected
    ): int {
        $supplierName =
            $batch->metadata['supplier']
            ?? null;

        if (! $supplierName) {
            throw new RuntimeException(
                'Supplier document has no supplier.'
            );
        }

        $supplier = SupplierProfile::query()
            ->where(
                'supplier_key',
                $this->supplierKey(
                    $supplierName
                )
            )
            ->first();

        if (! $supplier) {
            throw new RuntimeException(
                'Supplier profile not found: '
                .$supplierName
            );
        }

        foreach ($detected as $item) {
            $asset = SupplierAsset::firstOrCreate(
                [
                    'supplier_profile_id' => $supplier->id,
                    'asset_type' => $item['type'],
                    'asset_key' => $item['key'],
                ],
                [
                    'name' => $item['name'],
                    'confidence' => $item['confidence'],
                    'active' => true,
                    'first_seen_at' => $batch->created_at
                        ?->toDateString(),
                ]
            );

            $metadata =
                $asset->metadata ?? [];

            $documents =
                $metadata['source_documents']
                ?? [];

            if (
                ! in_array(
                    $batch->id,
                    $documents,
                    true
                )
            ) {
                $documents[] = $batch->id;
            }

            $updates = [
                'last_seen_at' => $batch->created_at
                    ?->toDateString(),
                'confidence' => max(
                    $asset->confidence,
                    $item['confidence']
                ),
                'metadata' => [
                    ...$metadata,
                    'source_documents' => $documents,
                ],
            ];

            if (
                isset($item['cost'])
                && $item['cost'] !== null
            ) {
                /*
                 * observed_cost here is the latest
                 * documented recurring/line-item cost,
                 * not cumulative lifetime spend.
                 */
                $updates['observed_cost'] =
                    $item['cost'];
            }

            $asset->update($updates);
        }

        return count($detected);
    }

    private function supplierKey(
        string $supplier
    ): string {
        return match (
            strtolower($supplier)
        ) {
            '20i' => '20i',
            'eukhost' => 'eukhost',
            default => strtolower($supplier),
        };
    }
}
