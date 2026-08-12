<?php

namespace App\Domains\ResourceIntelligence\Graph;

use App\Domains\ResourceIntelligence\Attribution\ResourceContributionRepository;
use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;

class ResourceContributionGraphProvider implements TruthGraphProvider
{
    public function __construct(
        private ResourceContributionRepository $repository
    ) {}

    public function supports(
        string $rootType
    ): bool {
        return $rootType === 'client';
    }

    public function build(
        string $rootId
    ): TruthGraphContribution {
        $nodes = collect();

        $edges = collect();

        foreach (
            $this->repository->all() as $attribution
        ) {
            $resourceNode =
                new TruthGraphNode(
                    type: 'resource',

                    id: $attribution->resource,

                    label: $attribution->resource,

                    attributes: [
                        'cost' => $attribution->cost,
                    ],

                    confidence: 90
                );

            $contributionNode =
                new TruthGraphNode(
                    type: 'contribution',

                    id: $attribution->workLogId,

                    label: 'Work contribution',

                    attributes: [
                        'hours' => $attribution->hours,

                        'value_created' => $attribution->valueCreated,

                        'margin' => $attribution->margin(),
                    ],

                    confidence: 90
                );

            $nodes->push(
                $resourceNode,
                $contributionNode
            );

            $edges->push(
                new TruthGraphEdge(
                    from: 'client:'.$rootId,
                    to: $contributionNode->key(),
                    relationship: 'received_contribution',
                    confidence: 90
                ),

                new TruthGraphEdge(
                    from: $contributionNode->key(),
                    to: $resourceNode->key(),
                    relationship: 'delivered_by',
                    confidence: 90
                )
            );
        }

        return new TruthGraphContribution(
            nodes: $nodes,
            edges: $edges
        );
    }
}
