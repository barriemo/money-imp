<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Discovery\ClientServiceAssetDiscoveryService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientServiceAssetDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_services_are_discovered_from_invoice_items(): void
    {
        $client = Client::factory()->create();

        $invoiceId = (string) str()->uuid();

        DB::table(
            'accounting_invoices'
        )->insert([
            'id' => $invoiceId,
            'client_id' => $client->id,
            'invoice_number' => '2079',
            'status' => 'paid',
            'invoice_date' => '2026-05-29',
            'currency' => 'GBP',
            'net_amount' => 505,
            'tax_amount' => 101,
            'gross_amount' => 606,
            'paid_amount' => 606,
            'outstanding_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [
                'description' => 'Monthly Hosting April & May 26',
                'quantity' => 2,
                'unit_price' => 185,
                'net_amount' => 370,
            ],
            [
                'description' => 'Postmark email',
                'quantity' => 1,
                'unit_price' => 170,
                'net_amount' => 170,
            ],
            [
                'description' => 'Google Workspace Annual Renewal.',
                'quantity' => 1,
                'unit_price' => 100,
                'net_amount' => 100,
            ],
            [
                'description' => 'Domain Annual Renewal - mindingkids.app',
                'quantity' => 1,
                'unit_price' => 50,
                'net_amount' => 50,
            ],
        ] as $row) {
            DB::table(
                'accounting_invoice_items'
            )->insert([
                'id' => (string) str()->uuid(),

                'accounting_invoice_id' => $invoiceId,

                'description' => $row['description'],

                'quantity' => $row['quantity'],

                'unit_price' => $row['unit_price'],

                'net_amount' => $row['net_amount'],

                'tax_rate' => 20,

                'tax_amount' => $row['net_amount'] * 0.2,

                'gross_amount' => $row['net_amount'] * 1.2,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $items = app(
            ClientServiceAssetDiscoveryService::class
        )->discover($client);

        $this->assertCount(
            4,
            $items
        );

        $this->assertTrue(
            $items->contains(
                fn (array $item) => $item['type']
                        === 'hosting'
            )
        );

        $this->assertTrue(
            $items->contains(
                fn (array $item) => $item['type']
                        === 'email_delivery'
                    && $item['key']
                        === 'postmark'
            )
        );

        $this->assertTrue(
            $items->contains(
                fn (array $item) => $item['type']
                        === 'workspace'
                    && $item['key']
                        === 'google-workspace'
            )
        );

        $this->assertTrue(
            $items->contains(
                fn (array $item) => $item['type']
                        === 'domain'
                    && $item['key']
                        === 'mindingkids.app'
            )
        );
    }
}
