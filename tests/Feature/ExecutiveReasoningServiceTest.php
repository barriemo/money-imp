<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoningService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveReasoningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_revenue_becomes_scored_executive_opportunity(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Reasoning Client',

                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-REASONING',

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

        $opportunities =
            app(
                ExecutiveReasoningService::class
            )->opportunities();

        $opportunity =
            $opportunities
                ->first(
                    fn ($item) => $item->clientId === $client->id
                        && $item->type === 'financial_opportunity'
                );

        $this->assertNotNull(
            $opportunity
        );

        $this->assertSame(
            'Recover overdue revenue',
            $opportunity->title
        );

        $this->assertSame(
            12000.0,
            $opportunity->estimatedFinancialImpact
        );

        $this->assertSame(
            10,
            $opportunity->estimatedEffortMinutes
        );

        $this->assertSame(
            100,
            $opportunity->confidence
        );

        $this->assertGreaterThanOrEqual(
            90,
            $opportunity->score
        );
    }
}
