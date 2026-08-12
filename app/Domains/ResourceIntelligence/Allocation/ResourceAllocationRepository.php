<?php

namespace App\Domains\ResourceIntelligence\Allocation;

use Illuminate\Support\Collection;

class ResourceAllocationRepository
{
    private Collection $items;

    public function __construct()
    {
        $this->items = collect();
    }

    public function add(
        array $allocation
    ): void {
        $this->items->push(
            $allocation
        );
    }

    public function all(): Collection
    {
        return $this->items;
    }
}
