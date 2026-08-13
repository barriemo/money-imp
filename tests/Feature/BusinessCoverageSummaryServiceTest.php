<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessCoverageSummaryService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCoverageSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_coverage_summary_identifies_missing_truth(): void
    {
        Client::factory()->create([
            'status' => 'active',
        ]);

        $summary =
            app(
                BusinessCoverageSummaryService::class
            )->current();

        $this->assertSame(
            1,
            $summary->clientCount
        );

        $this->assertSame(
            1,
            $summary->clientsWithoutWorkLogs
        );

        $this->assertSame(
            1,
            $summary->clientsWithoutServices
        );
    }
}
