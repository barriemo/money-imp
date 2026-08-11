<?php

namespace App\Domains\Infrastructure\Services;

use App\Domains\Infrastructure\Actions\LinkInfrastructureAssets;
use App\Models\SupplierAsset;

class InfrastructureGraphBuilder
{
    public function __construct(
        private LinkInfrastructureAssets $link
    ) {}

    public function build(): array
    {
        $summary = [
            'relationships_created' => 0,
            'assets_examined' => 0,
        ];

        SupplierAsset::query()
            ->with('supplier')
            ->get()
            ->each(
                function (
                    SupplierAsset $asset
                ) use (&$summary): void {
                    $summary[
                        'assets_examined'
                    ]++;

                    if (
                        $asset->asset_type
                        !== 'hosting_addon'
                    ) {
                        return;
                    }

                    $parentKey =
                        data_get(
                            $asset->metadata,
                            'parent_key'
                        );

                    if (! $parentKey) {
                        /*
                         * Earlier extractors may have
                         * encoded the parent only inside
                         * the asset key.
                         */
                        $parentKey =
                            $this->inferParentKey(
                                $asset
                            );
                    }

                    if (! $parentKey) {
                        return;
                    }

                    $parent =
                        SupplierAsset::query()
                            ->where(
                                'supplier_profile_id',
                                $asset
                                    ->supplier_profile_id
                            )
                            ->where(
                                'asset_type',
                                'hosting_server'
                            )
                            ->where(
                                'asset_key',
                                $parentKey
                            )
                            ->first();

                    if (! $parent) {
                        return;
                    }

                    $before =
                        $parent
                            ->outgoingRelationships()
                            ->where(
                                'to_asset_id',
                                $asset->id
                            )
                            ->where(
                                'relationship',
                                'PROVIDES'
                            )
                            ->exists();

                    $this->link->execute(
                        $parent,
                        $asset,
                        'PROVIDES',
                        100,
                        'supplier_invoice',
                        true
                    );

                    if (! $before) {
                        $summary[
                            'relationships_created'
                        ]++;
                    }
                }
            );

        return $summary;
    }

    private function inferParentKey(
        SupplierAsset $asset
    ): ?string {
        if (
            preg_match(
                '/^(.+?)-cpanel-/',
                $asset->asset_key,
                $match
            )
        ) {
            return $match[1];
        }

        return null;
    }
}
