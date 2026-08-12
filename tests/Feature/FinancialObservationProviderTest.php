<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Providers\FinancialObservationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialObservationProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_truth_becomes_business_observations(): void
    {
        $observations = app(
            FinancialObservationProvider::class
        )->observations();

        $this->assertCount(
            3,
            $observations
        );

        $this->assertNotNull(
            $observations->firstWhere(
                'type',
                'cash_position'
            )
        );

        $this->assertNotNull(
            $observations->firstWhere(
                'type',
                'liabilities'
            )
        );

        $this->assertNotNull(
            $observations->firstWhere(
                'type',
                'receivables'
            )
        );
    }
}
