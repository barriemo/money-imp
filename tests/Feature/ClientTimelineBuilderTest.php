<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Timeline\ClientTimelineBuilder;
use App\Models\AccountingInvoice;
use App\Models\BusinessMemoryEvent;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTimelineBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_timeline_combines_business_history(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Timeline Client',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-TIMELINE',

            'status' => 'paid',

            'invoice_date' => now()
                ->subDays(10),

            'due_date' => now()
                ->subDays(3),

            'currency' => 'GBP',

            'net_amount' => 1000,

            'tax_amount' => 200,

            'gross_amount' => 1200,

            'paid_amount' => 1200,

            'outstanding_amount' => 0,
        ]);

        BusinessMemoryEvent::create([
            'client_id' => $client->id,

            'client' => $client->name,

            'type' => 'decision_outcome',

            'source_type' => 'test',

            'source_id' => $client->id,

            'title' => 'Collections recommendation completed',

            'description' => 'Client paid outstanding balance.',

            'value' => 1200,

            'confidence' => 100,

            'occurred_at' => now(),
        ]);

        $timeline =
            app(
                ClientTimelineBuilder::class
            )->build(
                $client
            );

        $this->assertSame(
            'Timeline Client',
            $timeline->client->name
        );

        $this->assertCount(
            2,
            $timeline->events
        );

        $this->assertSame(
            'memory',
            $timeline->events
                ->first()
                ->type
        );

        $this->assertSame(
            'invoice',
            $timeline->events
                ->last()
                ->type
        );
    }
}
