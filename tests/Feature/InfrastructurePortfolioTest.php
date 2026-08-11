<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Services\InfrastructurePortfolioService;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InfrastructurePortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_portfolio_rolls_up_client_infrastructure_recovery(): void
    {
        $client = Client::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => 'Hosting Co',
            'supplier_key' => 'hosting co',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,

            'asset_type' => 'hosting_server',

            'asset_key' => 'server-a',

            'name' => 'Server A',

            'observed_cost' => 100,

            'client_id' => $client->id,

            'purpose' => 'client',

            'billable' => true,

            'active' => true,
        ]);

        SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,

            'asset_type' => 'hosting_server',

            'asset_key' => 'server-b',

            'name' => 'Server B',

            'observed_cost' => 80,

            'client_id' => $client->id,

            'purpose' => 'client',

            'billable' => true,

            'active' => true,
        ]);

        $invoiceId = (string) str()->uuid();

        DB::table(
            'accounting_invoices'
        )->insert([
            'id' => $invoiceId,
            'client_id' => $client->id,
            'invoice_number' => 'PORT-1',
            'status' => 'paid',
            'invoice_date' => '2026-08-01',
            'currency' => 'GBP',
            'net_amount' => 150,
            'tax_amount' => 30,
            'gross_amount' => 180,
            'paid_amount' => 180,
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
            'unit_price' => 150,
            'net_amount' => 150,
            'tax_rate' => 20,
            'tax_amount' => 30,
            'gross_amount' => 180,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = app(
            InfrastructurePortfolioService::class
        )->reconcile();

        $this->assertSame(
            2,
            $summary['asset_count']
        );

        $this->assertSame(
            180.0,
            $summary['monthly_cost']
        );

        /*
         * Both assets currently see the same
         * explicit client hosting line.
         *
         * This deliberately exposes the next
         * problem we need to solve:
         * invoice recovery allocation across
         * multiple assets.
         */
        $this->assertSame(
            300.0,
            $summary['monthly_recovery']
        );

        $this->assertSame(
            120.0,
            $summary['monthly_margin']
        );

        $this->assertSame(
            2,
            $summary['covered']
        );
    }
}
