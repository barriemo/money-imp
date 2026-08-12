<?php

namespace App\Domains\TruthGraph;

use Illuminate\Support\Collection;

class TruthGraphContribution
{
    public function __construct(
        public Collection $nodes,
        public Collection $edges
    ) {}

    public static function empty(): self
    {
        return new self(
            nodes: collect(),
            edges: collect()
        );
    }

    public function merge(
        self $other
    ): self {
        $nodes =
            $this->nodes
                ->concat(
                    $other->nodes
                )
                ->unique(
                    fn (TruthGraphNode $node) => $node->key()
                )
                ->values();

        $edges =
            $this->edges
                ->concat(
                    $other->edges
                )
                ->unique(
                    fn (TruthGraphEdge $edge) => $edge->key()
                )
                ->values();

        return new self(
            nodes: $nodes,
            edges: $edges
        );
    }
}
