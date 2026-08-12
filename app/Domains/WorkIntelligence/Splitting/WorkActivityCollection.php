<?php

namespace App\Domains\WorkIntelligence\Splitting;

use Illuminate\Support\Collection;

class WorkActivityCollection
{
    public function __construct(
        public Collection $items
    ) {}

    public function count(): int
    {
        return $this->items->count();
    }
}
