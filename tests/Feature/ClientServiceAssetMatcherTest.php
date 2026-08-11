<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Discovery\ClientServiceAssetMatcher;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientServiceAssetMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosting_service_matches_client_hosting_asset(): void
    {
        $client = Client::factory()->create();

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
            'net_amount' => 185,
            'tax_amount' => 37,
            'gross_amount' => 222,
            'paid_amount' => 222,
            'outstanding_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'accounting_invoice_items'
        )->insert([
            'id' => (string) str()->uuid(),
            'accounting_invoice_id' => $invoiceId,
            'description' => 'Monthly Hosting',
            'quantity' => 1,
            'unit_price' => 185,
            'net_amount' => 185,
            'tax_rate' => 20,
            'tax_amount' => 37,
            'gross_amount' => 222,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = app(
            ClientServiceAssetMatcher::class
        )->match($client);

        $hosting = $results->firstWhere(
            'type',
            'hosting'
        );

        $this->assertSame(
            'MATCHED',
            $hosting['status']
        );

        $this->assertSame(
            1,
            $hosting['match_count']
        );

        $this->assertSame(
            'server-1',
            $hosting['matches']
                ->first()
                ->asset_key
        );
    }
}
