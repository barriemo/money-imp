<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Interrogation\Attention\ClientAttentionService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAttentionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_are_ranked_by_attention_requirement(): void
    {
        $highRisk =
            Client::factory()->create([
                'name' => 'High Risk Client',
                'status' => 'active',
            ]);

        $lowRisk =
            Client::factory()->create([
                'name' => 'Low Risk Client',
                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $highRisk->id,

            'invoice_number' => 'INV-HIGH',

            'status' => 'open',

            'invoice_date' => now()->subMonth(),

            'due_date' => now()->subDays(14),

            'currency' => 'GBP',

            'net_amount' => 10000,

            'tax_amount' => 2000,

            'gross_amount' => 12000,

            'paid_amount' => 0,

            'outstanding_amount' => 12000,
        ]);

        AccountingInvoice::create([
            'client_id' => $lowRisk->id,

            'invoice_number' => 'INV-LOW',

            'status' => 'open',

            'invoice_date' => now(),

            'due_date' => now()->addDays(14),

            'currency' => 'GBP',

            'net_amount' => 500,

            'tax_amount' => 100,

            'gross_amount' => 600,

            'paid_amount' => 0,

            'outstanding_amount' => 600,
        ]);

        $ranked =
            app(
                ClientAttentionService::class
            )->ranked();

        $this->assertSame(
            'High Risk Client',
            $ranked->first()->client
        );

        $this->assertSame(
            12000.0,
            $ranked->first()->overdue
        );

        $this->assertGreaterThan(
            $ranked->last()->score,
            $ranked->first()->score
        );
    }

    public function test_dormant_billing_increases_attention(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Dormant Client',
                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-DORMANT',

            'status' => 'paid',

            'invoice_date' => now()->subDays(76),

            'due_date' => now()->subDays(60),

            'currency' => 'GBP',

            'net_amount' => 500,

            'tax_amount' => 100,

            'gross_amount' => 600,

            'paid_amount' => 600,

            'outstanding_amount' => 0,
        ]);

        $position =
            app(
                ClientAttentionService::class
            )->position(
                $client
            );

        $this->assertTrue(
            $position->billingDormant
        );

        $this->assertGreaterThanOrEqual(
            75,
            $position->daysSinceLastInvoice
        );

        $this->assertGreaterThan(
            0,
            $position->score
        );

        $this->assertNotEmpty(
            $position->reasons
        );
    }
}
