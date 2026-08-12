<?php

namespace App\Domains\ResourceIntelligence\Allocation;

use Illuminate\Support\Collection;

class AllocationVarianceRepository
{
    private Collection $variances;

    public function __construct()
    {
        $this->variances = collect();
    }

    public function add(
        AllocationVariance $variance
    ): void {
        $this->variances->push(
            $variance
        );
    }

    public function findForClient(
        string $clientId
    ): Collection {
        return $this->variances;
    }
}
