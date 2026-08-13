<?php

namespace App\Domains\VATIntelligence;

use Illuminate\Support\Collection;

class VATPositionRepository
{
    private Collection $positions;

    public function __construct()
    {
        $this->positions = collect();
    }

    public function add(
        string $clientId,
        VATPosition $position
    ): void {
        $this->positions->put(
            $clientId,
            $position
        );
    }

    public function findForClient(
        string $clientId
    ): ?VATPosition {
        return $this->positions->get(
            $clientId
        );
    }
}
