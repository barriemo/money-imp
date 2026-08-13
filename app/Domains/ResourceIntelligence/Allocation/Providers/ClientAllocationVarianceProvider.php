<?php

namespace App\Domains\ResourceIntelligence\Allocation\Providers;

use App\Domains\ResourceIntelligence\Allocation\AllocationVarianceRepository;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummariser;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;

class ClientAllocationVarianceProvider
{
    public function __construct(
        private AllocationVarianceRepository $repository,

        private AllocationVarianceSummariser $summariser
    ) {}

    public function provide(
        string $clientId
    ): AllocationVarianceSummary {
        return $this->summariser->summarise(
            $this->repository->findForClient(
                $clientId
            )
        );
    }
}
