<?php

namespace Tests\Feature;

use App\Domains\ManagedServices\Actions\CreateManagedService;
use App\Domains\ManagedServices\Costs\ManagedServiceCostAllocator;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedServiceCostAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_asset_cost_can_be_allocated_by_percentage(): void
    {
        $client =
            Client::factory()->create();

        $supplier =
            SupplierProfile::create([
                'supplier_name' => 'EUKhost',

                'supplier_key' => 'eukhost',

                'category' => 'hosting',

                'recoverable' => true,

                'active' => true,
            ]);

        $asset =
            SupplierAsset::create([
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

        $allocation = app(
            ManagedServiceCostAllocator::class
        )->allocatePercent(
            service: $service,
            asset: $asset,
            percent: 25,
            verified: true,
            source: 'test'
        );

        $this->assertSame(
            'percentage',
            $allocation
                ->allocation_method
        );

        $this->assertSame(
            '9.54',
            $allocation
                ->allocated_monthly_cost
        );

        $this->assertSame(
            '25.0000',
            $allocation
                ->allocation_percent
        );

        $this->assertTrue(
            $allocation->verified
        );
    }
}
