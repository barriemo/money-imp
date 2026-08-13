<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Decisions\BusinessDecisionService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_client_creates_collection_decision(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Overdue Client',
                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-001',

            'status' => 'overdue',

            'invoice_date' => now()->subMonth(),

            'due_date' => now()->subDays(14),

            'currency' => 'GBP',

            'net_amount' => 5000,

            'tax_amount' => 1000,

            'gross_amount' => 6000,

            'paid_amount' => 0,

            'outstanding_amount' => 6000,
        ]);

        $decision =
            app(
                BusinessDecisionService::class
            )->today()
                ->first();

        $this->assertNotNull(
            $decision
        );

        $this->assertSame(
            'collections',
            $decision->type
        );

        $this->assertSame(
            'Overdue Client',
            $decision->client
        );

        $this->assertSame(
            6000.0,
            $decision->value
        );
    }
}
