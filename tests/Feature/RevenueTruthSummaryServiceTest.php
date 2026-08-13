<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummaryService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueTruthSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_revenue_truth_summary_is_built_from_client_truth(): void
    {
        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-SUMMARY',

            'status' => 'overdue',

            'invoice_date' => now(),

            'due_date' => now()->subDays(7),

            'currency' => 'GBP',

            'net_amount' => 1000,

            'tax_amount' => 200,

            'gross_amount' => 1200,

            'paid_amount' => 0,

            'outstanding_amount' => 1200,
        ]);

        $summary =
            app(
                RevenueTruthSummaryService::class
            )->current();

        $this->assertSame(
            1,
            $summary->clientCount
        );

        $this->assertSame(
            1200.0,
            $summary->grossInvoiced
        );

        $this->assertSame(
            1200.0,
            $summary->outstanding
        );

        $this->assertSame(
            1,
            $summary->clientsWithOutstandingRevenue
        );

        $this->assertTrue(
            $summary->gaps
                ->contains(
                    fn ($gap) => $gap->type === 'outstanding_revenue'
                )
        );
    }
}
