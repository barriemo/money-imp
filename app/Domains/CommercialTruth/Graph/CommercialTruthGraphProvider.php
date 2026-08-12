<?php

namespace App\Domains\CommercialTruth\Graph;

use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;
use App\Models\Client;
use App\Models\CommercialAgreement;

class CommercialTruthGraphProvider implements TruthGraphProvider
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

        $agreements =
            CommercialAgreement::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->get();

        foreach (
            $agreements as $agreement
        ) {
            $node =
                new TruthGraphNode(
                    type: 'commercial_agreement',

                    id: $agreement->id,

                    label: ucfirst(
                        $agreement->service_type
                    )
                        .' agreement',

                    attributes: [
                        'service_type' => $agreement->service_type,

                        'service_key' => $agreement->service_key,

                        'cadence' => $agreement->cadence,

                        'status' => $agreement->status,

                        'observed_value' => (float)
                            $agreement
                                ->observed_value,

                        'monthly_equivalent' => (float)
                            $agreement
                                ->monthly_equivalent,
                    ],

                    confidence: $agreement->confidence
                );

            $nodes->push(
                $node
            );

            $edges->push(
                new TruthGraphEdge(
                    from: $clientKey,

                    to: $node->key(),

                    relationship: 'has_agreement',

                    confidence: $agreement->confidence,

                    evidence: [
                        'commercial_truth',
                    ]
                )
            );
        }

        return new TruthGraphContribution(
            nodes: $nodes,

            edges: $edges
        );
    }
}
