<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Graph\InfrastructureTruthGraphProvider;
use App\Domains\ManagedServices\Actions\LinkManagedServiceAsset;
use App\Models\Client;
use App\Models\ManagedService;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureTruthGraphProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_service_and_asset_contribute_to_client_graph(): void
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

        $server =
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

        $service =
            ManagedService::create([
                'client_id' => $client->id,

                'service_type' => 'managed_hosting',

                'name' => 'Managed Hosting',

                'status' => 'active',

                'billable' => true,

                'expected_monthly_revenue' => 150,

                'source' => 'test',

                'confidence' => 100,
            ]);

        app(
            LinkManagedServiceAsset::class
        )->execute(
            service: $service,

            asset: $server,

            role: 'primary',

            confidence: 100,

            verified: true,

            source: 'test'
        );

        $provider = app(
            InfrastructureTruthGraphProvider::class
        );

        $contribution =
            $provider->build(
                $client->id
            );

        $this->assertCount(
            2,
            $contribution->nodes
        );

        $this->assertCount(
            2,
            $contribution->edges
        );

        $serviceNode =
            $contribution
                ->nodes
                ->firstWhere(
                    'type',
                    'managed_service'
                );

        $assetNode =
            $contribution
                ->nodes
                ->firstWhere(
                    'type',
                    'supplier_asset'
                );

        $this->assertNotNull(
            $serviceNode
        );

        $this->assertNotNull(
            $assetNode
        );

        $this->assertSame(
            'Server 1',
            $assetNode->label
        );

        $this->assertTrue(
            $contribution
                ->edges
                ->contains(
                    fn ($edge) => $edge->relationship
                        === 'has_managed_service'
                )
        );

        $this->assertTrue(
            $contribution
                ->edges
                ->contains(
                    fn ($edge) => $edge->relationship
                        === 'uses_asset'
                )
        );
    }

    public function test_provider_ignores_unsupported_root_type(): void
    {
        $provider = app(
            InfrastructureTruthGraphProvider::class
        );

        $this->assertFalse(
            $provider->supports(
                'supplier_asset'
            )
        );
    }
}
