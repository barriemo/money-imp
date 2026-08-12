<?php

namespace App\Domains\TruthGraph;

use Illuminate\Support\Collection;

class TruthGraphQuery
{
    public function nodesOfType(
        array $graph,
        string $type
    ): Collection {
        return collect(
            $graph['nodes']
            ?? []
        )
            ->filter(
                fn (TruthGraphNode $node) => $node->type
                    === $type
            )
            ->values();
    }

    public function edgesFrom(
        array $graph,
        string $nodeKey
    ): Collection {
        return collect(
            $graph['edges']
            ?? []
        )
            ->filter(
                fn (TruthGraphEdge $edge) => $edge->from
                    === $nodeKey
            )
            ->values();
    }
}
