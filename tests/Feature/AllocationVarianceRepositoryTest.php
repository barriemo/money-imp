<?php

namespace Tests\Feature;

use App\Domains\ResourceIntelligence\Allocation\AllocationVarianceRepository;
use Tests\TestCase;

class AllocationVarianceRepositoryTest extends TestCase
{
    public function test_repository_returns_client_allocation_variances(): void
    {
        $variances =
            app(
                AllocationVarianceRepository::class
            )->findForClient(
                'client-1'
            );

        $this->assertCount(
            0,
            $variances
        );
    }
}
