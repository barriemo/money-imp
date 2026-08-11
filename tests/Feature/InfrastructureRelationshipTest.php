<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Actions\LinkInfrastructureAssets;
use App\Domains\Infrastructure\Services\InfrastructureGraphBuilder;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_can_be_linked(): void
    {
        $supplier = SupplierProfile::create([
            'supplier_name' => '20i',
            'supplier_key' => '20i',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        $server = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,
            'asset_type' => 'hosting_server',
            'asset_key' => 'imp1',
            'name' => 'Imp1',
            'observed_cost' => 89.99,
            'active' => true,
        ]);

        $storage = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,
            'asset_type' => 'storage',
            'asset_key' => 'timeline-storage',
            'name' => 'Timeline Storage',
            'observed_cost' => 1.35,
            'active' => true,
        ]);

        $relationship = app(
            LinkInfrastructureAssets::class
        )->execute(
            $server,
            $storage,
            'USES',
            95,
            'test',
            true
        );

        $this->assertSame(
            'USES',
            $relationship->relationship
        );

        $this->assertSame(
            $server->id,
            $relationship->from_asset_id
        );

        $this->assertSame(
            $storage->id,
            $relationship->to_asset_id
        );

        $this->assertTrue(
            $relationship->verified
        );
    }

    public function test_graph_builder_links_cpanel_to_server(): void
    {
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
            'asset_key' => '5-77-63-221-wk-ds-0517',
            'name' => '5.77.63.221#WK-DS-0517',
            'observed_cost' => 152.96,
            'active' => true,
        ]);

        $addon = SupplierAsset::create([
            'supplier_profile_id' => $supplier->id,
            'asset_type' => 'hosting_addon',
            'asset_key' => '5-77-63-221-wk-ds-0517-cpanel-premier',
            'name' => 'cPanel Premier',
            'observed_cost' => 38.14,
            'active' => true,
            'metadata' => [
                'parent_key' => '5-77-63-221-wk-ds-0517',
            ],
        ]);

        $summary = app(
            InfrastructureGraphBuilder::class
        )->build();

        $this->assertSame(
            1,
            $summary['relationships_created']
        );

        $this->assertDatabaseHas(
            'infrastructure_relationships',
            [
                'from_asset_id' => $server->id,
                'to_asset_id' => $addon->id,
                'relationship' => 'PROVIDES',
                'verified' => true,
            ]
        );
    }
}
