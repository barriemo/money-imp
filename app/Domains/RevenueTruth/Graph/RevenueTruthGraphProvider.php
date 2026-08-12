<?php

namespace App\Domains\RevenueTruth\Graph;

use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;
use App\Models\Client;
use App\Models\RevenueRecommendation;

class RevenueTruthGraphProvider implements TruthGraphProvider
{
    public function supports(
        string $rootType
    ): bool {
        return $rootType === 'client';
    }

    public function build(
        string $rootId
    ): TruthGraphContribution {
        $client = Client::query()
            ->find(
                $rootId
            );

        if (! $client) {
            return TruthGraphContribution::empty();
        }

        $nodes = collect();
        $edges = collect();

        $clientKey =
            'client:'.$client->id;

        $recommendations =
            RevenueRecommendation::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'status',
                    'open'
                )
                ->get();

        foreach ($recommendations as $recommendation) {
            $node =
                new TruthGraphNode(
                    type: 'revenue_recommendation',

                    id: $recommendation->id,

                    label: $recommendation->title,

                    attributes: [
                        'type' => $recommendation->type,

                        'status' => $recommendation->status,

                        'priority' => $recommendation->priority,

                        'estimated_monthly_value' => (float) $recommendation
                            ->estimated_monthly_value,

                        'estimated_annual_value' => (float) $recommendation
                            ->estimated_annual_value,

                        'recommended_action' => $recommendation
                            ->recommended_action,
                    ],

                    confidence: $recommendation
                        ->confidence
                );

            $nodes->push(
                $node
            );

            $edges->push(
                new TruthGraphEdge(
                    from: $clientKey,

                    to: $node->key(),

                    relationship: 'has_revenue_recommendation',

                    confidence: $recommendation
                        ->confidence,

                    evidence: [
                        'revenue_truth',
                    ]
                )
            );

            if (
                $recommendation
                    ->supplier_asset_id
            ) {
                $edges->push(
                    new TruthGraphEdge(
                        from: $node->key(),

                        to: 'supplier_asset:'
                            .$recommendation
                                ->supplier_asset_id,

                        relationship: 'concerns_asset',

                        confidence: $recommendation
                            ->confidence,

                        evidence: [
                            'revenue_truth',
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
