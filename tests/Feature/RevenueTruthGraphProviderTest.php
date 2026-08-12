<?php

namespace Tests\Feature;

use App\Domains\RevenueTruth\Graph\RevenueTruthGraphProvider;
use App\Models\Client;
use App\Models\RevenueRecommendation;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueTruthGraphProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_revenue_recommendation_contributes_to_client_graph(): void
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

        $recommendation =
            RevenueRecommendation::create([
                'client_id' => $client->id,

                'supplier_asset_id' => $asset->id,

                'type' => 'missing_recovery',

                'status' => 'open',

                'priority' => 90,

                'confidence' => 95,

                'title' => 'Recover billing for Server 1',

                'description' => 'No corresponding client recovery was identified.',

                'recommended_action' => 'Review and recover billing if appropriate.',

                'estimated_monthly_value' => 100,

                'estimated_annual_value' => 1200,
            ]);

        $provider = app(
            RevenueTruthGraphProvider::class
        );

        $contribution =
            $provider->build(
                $client->id
            );

        $this->assertCount(
            1,
            $contribution->nodes
        );

        $this->assertCount(
            2,
            $contribution->edges
        );

        $node =
            $contribution
                ->nodes
                ->first();

        $this->assertSame(
            'revenue_recommendation',
            $node->type
        );

        $this->assertSame(
            $recommendation->title,
            $node->label
        );

        $this->assertTrue(
            $contribution
                ->edges
                ->contains(
                    fn ($edge) => $edge->relationship
                        === 'has_revenue_recommendation'
                )
        );

        $this->assertTrue(
            $contribution
                ->edges
                ->contains(
                    fn ($edge) => $edge->relationship
                        === 'concerns_asset'
                        &&
                        $edge->to
                        === 'supplier_asset:'
                        .$asset->id
                )
        );
    }

    public function test_resolved_recommendation_is_not_contributed(): void
    {
        $client =
            Client::factory()->create();

        RevenueRecommendation::create([
            'client_id' => $client->id,

            'type' => 'missing_recovery',

            'status' => 'resolved',

            'priority' => 90,

            'confidence' => 95,

            'title' => 'Resolved recovery',

            'estimated_monthly_value' => 100,

            'estimated_annual_value' => 1200,
        ]);

        $contribution = app(
            RevenueTruthGraphProvider::class
        )->build(
            $client->id
        );

        $this->assertCount(
            0,
            $contribution->nodes
        );

        $this->assertCount(
            0,
            $contribution->edges
        );
    }
}
