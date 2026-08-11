<?php

namespace Tests\Feature;

use App\Domains\Suppliers\Assets\Actions\ReviewSupplierAsset;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAssetReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_can_be_assigned_and_marked_billable(): void
    {
        $client = Client::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => '20i',
            'supplier_key' => '20i',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        $asset = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,

            'asset_type' => 'hosting_server',

            'asset_key' => 'imp1',

            'name' => 'Imp1',

            'observed_cost' => 89.99,

            'active' => true,
        ]);

        app(
            ReviewSupplierAsset::class
        )->execute(
            $asset,
            'client',
            $client->id,
            true,
            125.00,
            'Monthly managed hosting'
        );

        $asset->refresh();

        $this->assertSame(
            'client',
            $asset->purpose
        );

        $this->assertSame(
            $client->id,
            $asset->client_id
        );

        $this->assertTrue(
            $asset->billable
        );

        $this->assertSame(
            125.0,
            (float)
            $asset->expected_charge
        );
    }

    public function test_cancelled_asset_is_inactive(): void
    {
        $supplier = SupplierProfile::create([
            'supplier_name' => '20i',
            'supplier_key' => '20i',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        $asset = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,

            'asset_type' => 'storage',
            'asset_key' => 'old-storage',

            'name' => 'Old Storage',

            'observed_cost' => 20,
            'active' => true,
        ]);

        app(
            ReviewSupplierAsset::class
        )->execute(
            $asset,
            'cancel',
            null,
            false,
            null,
            'No longer required'
        );

        $this->assertFalse(
            $asset->refresh()->active
        );
    }
}
