<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoningSummaryService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveReasoningSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_summary_identifies_high_value_quick_win(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Executive Opportunity Client',

                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-EXEC-001',

            'status' => 'overdue',

            'invoice_date' => now()
                ->subDays(30),

            'due_date' => now()
                ->subDays(20),

            'currency' => 'GBP',

            'net_amount' => 10000,

            'tax_amount' => 2000,

            'gross_amount' => 12000,

            'paid_amount' => 0,

            'outstanding_amount' => 12000,
        ]);

        $summary =
            app(
                ExecutiveReasoningSummaryService::class
            )->current();

        $this->assertNotNull(
            $summary->highestOpportunity
        );

        $this->assertSame(
            'Executive Opportunity Client',
            $summary->highestOpportunity->client
        );

        $this->assertSame(
            12000.0,
            $summary->knownFinancialImpact
        );

        $this->assertGreaterThanOrEqual(
            1,
            $summary->quickWinCount
        );

        $this->assertGreaterThanOrEqual(
            12000.0,
            $summary->quickWinFinancialImpact
        );
    }
}
