<?php

namespace Tests\Feature;

use App\Domains\RevenueTruth\RevenueRecommendationEngine;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueLeakageTest extends TestCase
{
    use RefreshDatabase;

    public function test_covered_asset_does_not_create_recovery_recommendation(): void
    {
        $client =
            Client::factory()->create();

        $supplier =
            SupplierProfile::create([
                'supplier_name' => 'Hosting Supplier',

                'supplier_key' => 'hosting-supplier',

                'category' => 'hosting',

                'recoverable' => true,

                'active' => true,
            ]);

        $asset =
            SupplierAsset::create([
                'supplier_profile_id' => $supplier->id,

                'client_id' => $client->id,

                'asset_type' => 'hosting_server',

                'asset_key' => 'server-1',

                'name' => 'Server 1',

                'purpose' => 'client',

                'billable' => true,

                'active' => true,

                'observed_cost' => 100,

                'confidence' => 100,
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'TEST-1',

                'invoice_date' => now()->toDateString(),

                'status' => 'sent',
            ]);

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,

            'description' => 'Monthly Hosting',

            'quantity' => 1,

            'unit_price' => 150,

            'net_amount' => 150,
        ]);

        $recommendation = app(
            RevenueRecommendationEngine::class
        )->recommend(
            $asset
        );

        $this->assertNull(
            $recommendation
        );

        $this->assertDatabaseCount(
            'revenue_recommendations',
            0
        );
    }
}
