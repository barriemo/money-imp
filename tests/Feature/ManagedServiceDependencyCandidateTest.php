<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Actions\LinkInfrastructureAssets;
use App\Domains\ManagedServices\Actions\CreateManagedService;
use App\Domains\ManagedServices\Actions\LinkManagedServiceAsset;
use App\Domains\ManagedServices\Discovery\ManagedServiceDependencyCandidateService;
use App\Models\Client;
use App\Models\ManagedServiceRequirement;
use App\Models\ManagedServiceTemplate;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedServiceDependencyCandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_dependency_is_proposed_for_missing_service_component(): void
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

        $template =
            ManagedServiceTemplate::create([
                'service_type' => 'managed_hosting',

                'name' => 'Managed Hosting',

                'active' => true,
            ]);

        foreach ([
            ['hosting_server', 'Server'],
            ['control_panel', 'Control Panel'],
        ] as [$type, $name]) {
            ManagedServiceRequirement::create([
                'managed_service_template_id' => $template->id,

                'component_type' => $type,

                'name' => $name,

                'required' => true,

                'minimum_count' => 1,

                'weight' => 1,
            ]);
        }

        $server =
            SupplierAsset::create([
                'supplier_profile_id' => $supplier->id,

                'asset_type' => 'hosting_server',

                'asset_key' => 'server-1',

                'name' => 'Server 1',

                'observed_cost' => 152.96,

                'active' => true,
            ]);

        $cpanel =
            SupplierAsset::create([
                'supplier_profile_id' => $supplier->id,

                'asset_type' => 'hosting_addon',

                'asset_key' => 'cpanel',

                'name' => 'cPanel',

                'observed_cost' => 38.14,

                'active' => true,
            ]);

        app(
            LinkInfrastructureAssets::class
        )->execute(
            from: $server,
            to: $cpanel,
            relationship: 'PROVIDES',
            confidence: 100,
            source: 'supplier_invoice',
            verified: true
        );

        $service = app(
            CreateManagedService::class
        )->execute(
            client: $client,
            type: 'managed_hosting',
            name: 'Managed Hosting',
            expectedMonthlyRevenue: 185
        );

        app(
            LinkManagedServiceAsset::class
        )->execute(
            service: $service,
            asset: $server,
            role: 'primary',
            verified: true
        );

        $candidates = app(
            ManagedServiceDependencyCandidateService::class
        )->candidates(
            $service
        );

        $this->assertCount(
            1,
            $candidates
        );

        $candidate =
            $candidates->first();

        $this->assertSame(
            $cpanel->id,
            $candidate['asset']->id
        );

        $this->assertSame(
            'control_panel',
            $candidate['component_type']
        );

        $this->assertSame(
            'PROVIDES',
            $candidate['relationship']
        );

        $this->assertTrue(
            $candidate[
                'verified_relationship'
            ]
        );

        $this->assertTrue(
            $candidate['recommended']
        );
    }
}
