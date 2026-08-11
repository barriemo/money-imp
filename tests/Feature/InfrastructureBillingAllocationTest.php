<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Billing\InfrastructureBillingAllocator;
use App\Models\Client;
use App\Models\InfrastructureBillingAllocation;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InfrastructureBillingAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_is_allocated_without_double_counting(): void
    {
        $client = Client::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => 'EUKhost',
            'supplier_key' => 'eukhost',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        foreach ([
            [
                'key' => 'server',
                'name' => 'Server',
                'type' => 'hosting_server',
                'cost' => 152.96,
            ],
            [
                'key' => 'cpanel',
                'name' => 'cPanel',
                'type' => 'hosting_addon',
                'cost' => 38.14,
            ],
            [
                'key' => 'storage',
                'name' => 'Storage',
                'type' => 'storage',
                'cost' => 12.00,
            ],
        ] as $item) {
            SupplierAsset::create([
                'supplier_profile_id' => $supplier->id,

                'asset_type' => $item['type'],

                'asset_key' => $item['key'],

                'name' => $item['name'],

                'observed_cost' => $item['cost'],

                'client_id' => $client->id,

                'purpose' => 'client',

                'billable' => true,

                'active' => true,
            ]);
        }

        $invoiceId = (string) str()->uuid();
        $itemId = (string) str()->uuid();

        DB::table(
            'accounting_invoices'
        )->insert([
            'id' => $invoiceId,
            'client_id' => $client->id,
            'invoice_number' => '2079',
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
            'id' => $itemId,
            'accounting_invoice_id' => $invoiceId,
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

        $summary = app(
            InfrastructureBillingAllocator::class
        )->allocate($client);

        $this->assertSame(
            'ALLOCATED',
            $summary['status']
        );

        $this->assertSame(
            3,
            $summary['assets']
        );

        $this->assertSame(
            185.0,
            $summary['available_recovery']
        );

        $this->assertSame(
            185.0,
            $summary['allocated_recovery']
        );

        $this->assertSame(
            185.0,
            round(
                (float)
                InfrastructureBillingAllocation::query()
                    ->sum('allocated_amount'),
                2
            )
        );

        $this->assertCount(
            3,
            InfrastructureBillingAllocation::all()
        );
    }
}
