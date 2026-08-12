<?php

namespace App\Domains\WorkIntelligence\Graph;

use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;

class WorkTruthGraphProvider implements TruthGraphProvider
{
    public function supports(
        string $rootType
    ): bool {
        return $rootType === 'work_event';
    }

    public function build(
        string $rootId
    ): TruthGraphContribution {
        $workNode =
            new TruthGraphNode(
                type: 'work_event',
                id: $rootId,
                label: 'Completed client work',
                attributes: [
                    'billable' => true,
                ],
                confidence: 85
            );

        $clientNode =
            new TruthGraphNode(
                type: 'client',
                id: 'unknown',
                label: 'Unknown client',
                confidence: 50
            );

        $edge =
            new TruthGraphEdge(
                from: $clientNode->key(),
                to: $workNode->key(),
                relationship: 'received_work',
                confidence: 85
            );

        return new TruthGraphContribution(
            nodes: collect([
                $clientNode,
                $workNode,
            ]),
            edges: collect([
                $edge,
            ])
        );
    }
}
