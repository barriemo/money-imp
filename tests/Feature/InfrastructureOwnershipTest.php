<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Services\InfrastructureOwnershipService;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_owned_asset_is_exposed(): void
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

            'asset_key' => '5-77-63-221-wk-ds-0517',

            'name' => '5.77.63.221#WK-DS-0517',

            'observed_cost' => 152.96,

            'client_id' => $client->id,

            'purpose' => 'client',

            'billable' => true,

            'expected_charge' => 200,

            'active' => true,
        ]);

        $items = app(
            InfrastructureOwnershipService::class
        )->ownedAssets();

        $this->assertCount(
            1,
            $items
        );

        $item = $items->first();

        $this->assertSame(
            $client->id,
            $item['client']->id
        );

        $this->assertSame(
            152.96,
            $item['monthly_cost']
        );

        $this->assertTrue(
            $item['billable']
        );

        $this->assertSame(
            200.0,
            $item['expected_charge']
        );

        $this->assertSame(
            'EUKhost',
            $item['supplier']->supplier_name
        );
    }
}
