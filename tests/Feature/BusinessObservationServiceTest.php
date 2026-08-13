<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Observations\BusinessObservationService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessObservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_observations_are_created_from_current_decisions(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Observation Client',
                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-OBS',

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

        $observation =
            app(
                BusinessObservationService::class
            )->current()
                ->first();

        $this->assertNotNull(
            $observation
        );

        $this->assertSame(
            'collections',
            $observation->type
        );

        $this->assertSame(
            'Observation Client',
            $observation->client
        );

        $this->assertSame(
            6000.0,
            $observation->value
        );
    }
}
