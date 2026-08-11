<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Services\ClientServiceGapService;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientServiceGapTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_cost_services_are_surfaced_as_gaps(): void
    {
        $client = Client::factory()->create([
            'status' => 'active',
        ]);

        $supplier = SupplierProfile::create([
            'supplier_name' => 'EUKhost',
            'supplier_key' => 'eukhost',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,

            'asset_type' => 'hosting_server',

            'asset_key' => 'server-1',

            'name' => 'Server 1',

            'observed_cost' => 152.96,

            'client_id' => $client->id,

            'purpose' => 'client',

            'billable' => true,

            'active' => true,
        ]);

        $invoiceId =
            (string) str()->uuid();

        DB::table(
            'accounting_invoices'
        )->insert([
            'id' => $invoiceId,
            'client_id' => $client->id,
            'invoice_number' => '2079',
            'status' => 'paid',
            'invoice_date' => '2026-05-29',
            'currency' => 'GBP',
            'net_amount' => 355,
            'tax_amount' => 71,
            'gross_amount' => 426,
            'paid_amount' => 426,
            'outstanding_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [
                'description' => 'Monthly Hosting',
                'unit_price' => 185,
            ],
            [
                'description' => 'Postmark email',
                'unit_price' => 170,
            ],
        ] as $row) {
            DB::table(
                'accounting_invoice_items'
            )->insert([
                'id' => (string) str()->uuid(),

                'accounting_invoice_id' => $invoiceId,

                'description' => $row['description'],

                'quantity' => 1,

                'unit_price' => $row['unit_price'],

                'net_amount' => $row['unit_price'],

                'tax_rate' => 20,

                'tax_amount' => $row['unit_price'] * 0.2,

                'gross_amount' => $row['unit_price'] * 1.2,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $gaps = app(
            ClientServiceGapService::class
        )->forClient($client);

        $this->assertCount(
            1,
            $gaps
        );

        $this->assertSame(
            'email_delivery',
            $gaps->first()['type']
        );

        $this->assertSame(
            'COST_UNKNOWN',
            $gaps->first()['status']
        );

        $this->assertSame(
            'Find supplier email delivery cost',
            $gaps->first()['action']
        );
    }
}
