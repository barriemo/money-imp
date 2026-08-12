<?php

namespace App\Domains\Evidence;

use Illuminate\Support\Collection;

class EvidenceRepository
{
    public function __construct(
        private Collection $items = new Collection
    ) {}

    public function add(
        EvidenceItem $item
    ): void {
        $this->items->push(
            $item
        );
    }

    public function all(): Collection
    {
        return $this->items;
    }
}
