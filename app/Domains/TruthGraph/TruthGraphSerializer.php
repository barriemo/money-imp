<?php

namespace App\Domains\TruthGraph;

class TruthGraphSerializer
{
    public function toArray(
        array $graph
    ): array {
        return [
            'root' => $graph['root'],

            'nodes' => collect(
                $graph['nodes']
            )
                ->map(
                    fn (TruthGraphNode $node) => [
                        'key' => $node->key(),

                        'type' => $node->type,

                        'id' => $node->id,

                        'label' => $node->label,

                        'attributes' => $node->attributes,

                        'confidence' => $node->confidence,
                    ]
                )
                ->values()
                ->all(),

            'edges' => collect(
                $graph['edges']
            )
                ->map(
                    fn (TruthGraphEdge $edge) => [
                        'from' => $edge->from,

                        'to' => $edge->to,

                        'relationship' => $edge->relationship,

                        'confidence' => $edge->confidence,

                        'evidence' => $edge->evidence,
                    ]
                )
                ->values()
                ->all(),
        ];
    }
}
