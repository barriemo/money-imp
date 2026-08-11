<?php

namespace Tests\Feature;

use App\Domains\ManagedServices\Actions\CreateManagedService;
use App\Domains\ManagedServices\Actions\LinkManagedServiceAsset;
use App\Domains\ManagedServices\Costs\ManagedServiceCostAllocator;
use App\Domains\ManagedServices\Services\ManagedServiceFinancialService;
use App\Models\Client;
use App\Models\ManagedServiceAsset;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_service_rolls_up_linked_asset_costs(): void
    {
        $client = Client::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => 'EUKhost',
            'supplier_key' => 'eukhost',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        $server = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,

            'asset_type' => 'hosting_server',

            'asset_key' => 'server-1',

            'name' => 'Server 1',

            'observed_cost' => 152.96,

            'active' => true,
        ]);

        $cpanel = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,

            'asset_type' => 'hosting_addon',

            'asset_key' => 'cpanel',

            'name' => 'cPanel',

            'observed_cost' => 38.14,

            'active' => true,
        ]);

        $service = app(
            CreateManagedService::class
        )->execute(
            client: $client,
            type: 'managed_hosting',
            name: 'Managed Hosting',
            billable: true,
            expectedMonthlyRevenue: 185,
            source: 'invoice_discovery'
        );

        ManagedServiceAsset::create([
            'managed_service_id' => $service->id,

            'supplier_asset_id' => $server->id,

            'role' => 'primary',

            'confidence' => 100,

            'verified' => true,

            'source' => 'test',
        ]);

        ManagedServiceAsset::create([
            'managed_service_id' => $service->id,

            'supplier_asset_id' => $cpanel->id,

            'role' => 'dependency',

            'confidence' => 100,

            'verified' => true,

            'source' => 'test',
        ]);

        $summary = app(
            ManagedServiceFinancialService::class
        )->summary(
            $service
        );

        $this->assertSame(
            191.10,
            $summary['monthly_cost']
        );

        $this->assertSame(
            185.0,
            $summary['monthly_revenue']
        );

        $this->assertSame(
            -6.10,
            $summary['monthly_margin']
        );

        $this->assertSame(
            2,
            $summary['asset_count']
        );
    }

    public function test_shared_asset_uses_allocated_cost_in_service_financials(): void
    {
        $client = Client::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => 'EUKhost',
            'supplier_key' => 'eukhost',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        $server = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,
            'asset_type' => 'hosting_server',
            'asset_key' => 'server-1',
            'name' => 'Server 1',
            'observed_cost' => 152.96,
            'active' => true,
        ]);

        $cpanel = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,
            'asset_type' => 'hosting_addon',
            'asset_key' => 'cpanel',
            'name' => 'cPanel',
            'observed_cost' => 38.14,
            'active' => true,
        ]);

        $service = app(
            CreateManagedService::class
        )->execute(
            client: $client,
            type: 'managed_hosting',
            name: 'Managed Hosting',
            expectedMonthlyRevenue: 185
        );

        $link = app(
            LinkManagedServiceAsset::class
        );

        $link->execute(
            service: $service,
            asset: $server,
            role: 'primary',
            verified: true
        );

        $link->execute(
            service: $service,
            asset: $cpanel,
            role: 'dependency',
            verified: true
        );

        app(
            ManagedServiceCostAllocator::class
        )->allocatePercent(
            service: $service,
            asset: $cpanel,
            percent: 25,
            verified: true,
            source: 'test'
        );

        $summary = app(
            ManagedServiceFinancialService::class
        )->summary($service);

        $this->assertSame(
            162.50,
            $summary['monthly_cost']
        );

        $this->assertSame(
            22.50,
            $summary['monthly_margin']
        );

        $cpanelLine = $summary[
            'cost_lines'
        ]->first(
            fn (array $line) => $line['asset']->id
                    === $cpanel->id
        );

        $this->assertSame(
            9.54,
            $cpanelLine['cost']
        );

        $this->assertSame(
            'allocation',
            $cpanelLine['cost_source']
        );
    }
}
