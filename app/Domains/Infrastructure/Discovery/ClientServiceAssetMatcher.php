<?php

namespace App\Domains\Infrastructure\Discovery;

use App\Models\Client;
use App\Models\SupplierAsset;
use Illuminate\Support\Collection;

class ClientServiceAssetMatcher
{
    public function __construct(
        private ClientServiceAssetDiscoveryService $discovery
    ) {}

    public function match(
        Client $client
    ): Collection {
        $proposals = $this->discovery
            ->discover($client);

        $assets = SupplierAsset::query()
            ->with('supplier')
            ->where('active', true)
            ->get();

        return $proposals->map(
            function (array $proposal) use (
                $client,
                $assets
            ): array {
                $matches = $this->matchesFor(
                    $client,
                    $proposal,
                    $assets
                );

                return [
                    ...$proposal,

                    'matches' => $matches,

                    'match_count' => $matches->count(),

                    'status' => match (true) {
                        $matches->count() === 1 => 'MATCHED',

                        $matches->count() > 1 => 'AMBIGUOUS',

                        default => 'COST_UNKNOWN',
                    },
                ];
            }
        );
    }

    private function matchesFor(
        Client $client,
        array $proposal,
        Collection $assets
    ): Collection {
        return match (
            $proposal['type']
        ) {
            'hosting' => $assets->filter(
                fn (SupplierAsset $asset) => $asset->client_id
                        === $client->id
                    && in_array(
                        $asset->asset_type,
                        [
                            'hosting_server',
                            'hosting_addon',
                            'storage',
                        ],
                        true
                    )
            )->values(),

            'domain' => $assets->filter(
                fn (SupplierAsset $asset) => $asset->asset_type
                        === 'domain'
                    && strtolower(
                        $asset->asset_key
                    ) === strtolower(
                        $proposal['key']
                    )
            )->values(),

            'email_delivery' => $assets->filter(
                fn (SupplierAsset $asset) => str_contains(
                    strtolower(
                        $asset->name
                    ),
                    strtolower(
                        $proposal['key']
                    )
                )
            )->values(),

            'workspace' => $assets->filter(
                fn (SupplierAsset $asset) => str_contains(
                    strtolower(
                        $asset->name
                    ),
                    'google workspace'
                )
            )->values(),

            default => collect(),
        };
    }
}
