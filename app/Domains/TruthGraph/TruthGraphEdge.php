<?php

namespace App\Domains\TruthGraph;

class TruthGraphEdge
{
    public function __construct(
        public string $from,
        public string $to,
        public string $relationship,
        public int $confidence = 100,
        public array $evidence = []
    ) {}

    public function key(): string
    {
        return hash(
            'sha256',
            implode('|', [
                $this->from,
                $this->relationship,
                $this->to,
            ])
        );
    }
}
