<?php

namespace App\Domains\ResourceIntelligence\Attribution;

use Illuminate\Support\Collection;

class ResourceContributionRepository
{
    private Collection $items;

    public function __construct()
    {
        $this->items = collect();
    }

    public function add(
        ResourceWorkAttribution $attribution
    ): void {
        $this->items->push(
            $attribution
        );
    }

    public function all(): Collection
    {
        return $this->items;
    }
}
