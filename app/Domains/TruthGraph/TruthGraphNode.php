<?php

namespace App\Domains\TruthGraph;

class TruthGraphNode
{
    public function __construct(
        public string $type,
        public string $id,
        public string $label,
        public array $attributes = [],
        public int $confidence = 100
    ) {}

    public function key(): string
    {
        return $this->type
            .':'
            .$this->id;
    }
}
