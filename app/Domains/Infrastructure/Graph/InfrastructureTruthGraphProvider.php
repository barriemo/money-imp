<?php

namespace App\Domains\Infrastructure\Graph;

use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;
use App\Models\Client;
use App\Models\ManagedService;

class InfrastructureTruthGraphProvider implements TruthGraphProvider
{
    public function supports(
        string $rootType
    ): bool {
        return $rootType === 'client';
    }

    public function build(
        string $rootId
    ): TruthGraphContribution {
        $client =
            Client::query()
                ->find(
                    $rootId
                );

        if (! $client) {
            return TruthGraphContribution::empty();
        }

        $nodes = collect();
        $edges = collect();

        $clientKey =
            'client:'
            .$client->id;

        $services =
            ManagedService::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->get();

        foreach ($services as $service) {
            $serviceNode =
                new TruthGraphNode(
                    type: 'managed_service',

                    id: $service->id,

                    label: $service->name,

                    attributes: [
                        'service_type' => $service->service_type,

                        'status' => $service->status,

                        'billable' => (bool) $service->billable,

                        'expected_monthly_revenue' => (float) $service
                            ->expected_monthly_revenue,
                    ],

                    confidence: (int) (
                        $service->confidence
                        ?? 100
                    )
                );

            $nodes->push(
                $serviceNode
            );

            $edges->push(
                new TruthGraphEdge(
                    from: $clientKey,

                    to: $serviceNode->key(),

                    relationship: 'has_managed_service',

                    confidence: (int) (
                        $service->confidence
                        ?? 100
                    ),

                    evidence: [
                        'managed_services',
                    ]
                )
            );

            $assets =
                $service
                    ->assets()
                    ->get();

            foreach ($assets as $asset) {
                $assetNode =
                    new TruthGraphNode(
                        type: 'supplier_asset',

                        id: $asset->id,

                        label: $asset->name,

                        attributes: [
                            'asset_type' => $asset->asset_type,

                            'purpose' => $asset->purpose,

                            'billable' => (bool) $asset->billable,

                            'active' => (bool) $asset->active,

                            'observed_cost' => (float) $asset->observed_cost,
                        ],

                        confidence: (int) (
                            $asset->confidence
                            ?? 100
                        )
                    );

                $nodes->push(
                    $assetNode
                );

                $pivotConfidence =
                    (int) (
                        $asset->pivot
                            ?->confidence
                        ?? 100
                    );

                $edges->push(
                    new TruthGraphEdge(
                        from: $serviceNode->key(),

                        to: $assetNode->key(),

                        relationship: 'uses_asset',

                        confidence: $pivotConfidence,

                        evidence: [
                            'managed_service_asset',
                        ]
                    )
                );
            }
        }

        return new TruthGraphContribution(
            nodes: $nodes,

            edges: $edges
        );
    }
}
