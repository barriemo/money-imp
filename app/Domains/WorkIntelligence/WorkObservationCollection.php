<?php

namespace App\Domains\WorkIntelligence;

use Illuminate\Support\Collection;

class WorkObservationCollection
{
    public function __construct(
        public Collection $items
    ) {}

    public function add(
        WorkObservation $observation
    ): self {
        $this->items->push(
            $observation
        );

        return $this;
    }
}
