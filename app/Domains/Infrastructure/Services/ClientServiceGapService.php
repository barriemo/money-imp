<?php

namespace App\Domains\Infrastructure\Services;

use App\Domains\Infrastructure\Discovery\ClientServiceAssetMatcher;
use App\Models\Client;
use Illuminate\Support\Collection;

class ClientServiceGapService
{
    public function __construct(
        private ClientServiceAssetMatcher $matcher
    ) {}

    public function forClient(
        Client $client
    ): Collection {
        return $this->matcher
            ->match($client)
            ->filter(
                fn (array $item) => in_array(
                    $item['status'],
                    [
                        'COST_UNKNOWN',
                        'AMBIGUOUS',
                    ],
                    true
                )
            )
            ->map(
                function (array $item): array {
                    return [
                        ...$item,

                        'action' => $this->actionFor(
                            $item
                        ),
                    ];
                }
            )
            ->values();
    }

    public function all(): Collection
    {
        return Client::query()
            ->where(
                'status',
                'active'
            )
            ->orderBy('name')
            ->get()
            ->flatMap(
                function (Client $client) {
                    return $this
                        ->forClient($client)
                        ->map(
                            fn (array $item) => [
                                ...$item,
                                'client' => $client,
                            ]
                        );
                }
            )
            ->values();
    }

    private function actionFor(
        array $item
    ): string {
        if (
            $item['status']
            === 'AMBIGUOUS'
        ) {
            return 'Review possible supplier asset matches';
        }

        return match (
            $item['type']
        ) {
            'email_delivery' => 'Find supplier email delivery cost',

            'workspace' => 'Find workspace licence cost',

            'domain' => 'Find registrar renewal cost',

            'hosting' => 'Find hosting supplier cost',

            default => 'Find supplier cost',
        };
    }
}
