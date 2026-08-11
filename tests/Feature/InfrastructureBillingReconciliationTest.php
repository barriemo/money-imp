<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Services\InfrastructureBillingReconciliationService;
use App\Models\Client;
use App\Models\InfrastructureBillingAllocation;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InfrastructureBillingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_explicit_hosting_charge_is_used_for_recovery(): void
    {
        $client = Client::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => 'EUKhost',
            'supplier_key' => 'eukhost',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        $asset = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,

            'asset_type' => 'hosting_server',

            'asset_key' => 'minding-kids-server',

            'name' => 'Minding Kids Server',

            'observed_cost' => 152.96,

            'client_id' => $client->id,

            'purpose' => 'client',

            'billable' => true,

            'active' => true,
        ]);

        $oldInvoiceId = (string) str()->uuid();

        DB::table(
            'accounting_invoices'
        )->insert([
            'id' => $oldInvoiceId,
            'client_id' => $client->id,
            'invoice_number' => '1001',
            'status' => 'paid',
            'invoice_date' => '2025-08-29',
            'currency' => 'GBP',
            'net_amount' => 100,
            'tax_amount' => 20,
            'gross_amount' => 120,
            'paid_amount' => 120,
            'outstanding_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'accounting_invoice_items'
        )->insert([
            'id' => (string) str()->uuid(),
            'accounting_invoice_id' => $oldInvoiceId,
            'description' => 'Monthly Hosting, Security Updates & Backups',
            'quantity' => 1,
            'unit_price' => 100,
            'net_amount' => 100,
            'tax_rate' => 20,
            'tax_amount' => 20,
            'gross_amount' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $latestInvoiceId =
            (string) str()->uuid();

        DB::table(
            'accounting_invoices'
        )->insert([
            'id' => $latestInvoiceId,
            'client_id' => $client->id,
            'invoice_number' => '2001',
            'status' => 'paid',
            'invoice_date' => '2026-05-29',
            'currency' => 'GBP',
            'net_amount' => 370,
            'tax_amount' => 74,
            'gross_amount' => 444,
            'paid_amount' => 444,
            'outstanding_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'accounting_invoice_items'
        )->insert([
            'id' => (string) str()->uuid(),
            'accounting_invoice_id' => $latestInvoiceId,
            'description' => 'Monthly Hosting April & May 26',
            'quantity' => 2,
            'unit_price' => 185,
            'net_amount' => 370,
            'tax_rate' => 20,
            'tax_amount' => 74,
            'gross_amount' => 444,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(
            InfrastructureBillingReconciliationService::class
        )->reconcile($asset);

        $this->assertSame(
            'COVERED',
            $result->status
        );

        $this->assertSame(
            152.96,
            $result->monthlyCost
        );

        $this->assertSame(
            185.0,
            $result->monthlyRecovery
        );

        $this->assertSame(
            32.04,
            $result->monthlyMargin
        );

        $this->assertSame(
            0.0,
            $result->monthlyGap
        );

        $this->assertSame(
            120.95,
            $result->coveragePercent
        );

        $this->assertSame(
            'Monthly Hosting April & May 26',
            $result->matchedDescription
        );
    }

    public function test_allocated_recovery_takes_precedence_over_full_invoice_line(): void
    {
        $client = Client::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => 'EUKhost',
            'supplier_key' => 'eukhost',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        $asset = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,
            'asset_type' => 'hosting_server',
            'asset_key' => 'server-a',
            'name' => 'Server A',
            'observed_cost' => 152.96,
            'client_id' => $client->id,
            'purpose' => 'client',
            'billable' => true,
            'active' => true,
        ]);

        $invoiceId = (string) str()->uuid();
        $itemId = (string) str()->uuid();

        DB::table('accounting_invoices')->insert([
            'id' => $invoiceId,
            'client_id' => $client->id,
            'invoice_number' => '3001',
            'status' => 'paid',
            'invoice_date' => '2026-08-01',
            'currency' => 'GBP',
            'net_amount' => 185,
            'tax_amount' => 37,
            'gross_amount' => 222,
            'paid_amount' => 222,
            'outstanding_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('accounting_invoice_items')->insert([
            'id' => $itemId,
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

        InfrastructureBillingAllocation::create([
            'supplier_asset_id' => $asset->id,
            'accounting_invoice_item_id' => $itemId,
            'allocated_amount' => 139.40,
            'confidence' => 100,
            'source' => 'proportional_cost',
            'verified' => false,
        ]);

        $result = app(
            InfrastructureBillingReconciliationService::class
        )->reconcile($asset);

        $this->assertSame(
            139.40,
            $result->monthlyRecovery
        );

        $this->assertSame(
            'UNDER_RECOVERED',
            $result->status
        );

        $this->assertSame(
            -13.56,
            $result->monthlyMargin
        );

        $this->assertSame(
            13.56,
            $result->monthlyGap
        );

        $this->assertSame(
            'allocated',
            $result->confidence
        );
    }
}
