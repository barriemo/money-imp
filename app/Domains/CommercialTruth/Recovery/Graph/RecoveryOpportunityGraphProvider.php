<?php

namespace App\Domains\CommercialTruth\Recovery\Graph;

use App\Domains\CommercialTruth\Recovery\RecoveryOpportunityFinder;
use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;
use App\Models\Client;

class RecoveryOpportunityGraphProvider implements TruthGraphProvider
{
    public function __construct(
        private RecoveryOpportunityFinder $finder
    ) {}

    public function supports(
        string $rootType
    ): bool {
        return $rootType === 'client';
    }

    public function build(
        string $rootId
    ): TruthGraphContribution {
        $opportunities =
            $this->finder->find(
                Client::findOrFail($rootId)
            );

        $nodes = collect();
        $edges = collect();

        foreach ($opportunities as $opportunity) {
            $node =
                new TruthGraphNode(
                    type: 'recovery_opportunity',

                    id: $opportunity->workLogId,

                    label: 'recovery_required',

                    attributes: [
                        'value' => $opportunity->value,

                        'reason' => $opportunity->reason,
                    ],

                    confidence: $opportunity->confidence
                );

            $nodes->push(
                $node
            );

            $edges->push(
                new TruthGraphEdge(
                    from: 'client:'.$rootId,

                    to: $node->key(),

                    relationship: 'has_recovery_opportunity',

                    confidence: $opportunity->confidence
                )
            );
        }

        return new TruthGraphContribution(
            nodes: $nodes,

            edges: $edges
        );
    }
}
