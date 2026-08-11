<?php

namespace Tests\Feature;

use App\Domains\ManagedServices\Actions\CreateManagedService;
use App\Domains\ManagedServices\Actions\LinkManagedServiceAsset;
use App\Domains\ManagedServices\Services\ManagedServiceTruthService;
use App\Models\Client;
use App\Models\ManagedServiceRequirement;
use App\Models\ManagedServiceTemplate;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedServiceTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_service_margin_is_provisional(): void
    {
        $client =
            Client::factory()->create();

        $supplier =
            SupplierProfile::create([
                'supplier_name' => 'Hosting Co',

                'supplier_key' => 'hosting-co',

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
            'hosting_server',
            'control_panel',
            'backup',
            'dns',
            'ssl',
        ] as $type) {
            ManagedServiceRequirement::create([
                'managed_service_template_id' => $template->id,

                'component_type' => $type,

                'name' => $type,

                'required' => true,

                'minimum_count' => 1,

                'weight' => 1,
            ]);
        }

        $server =
            SupplierAsset::create([
                'supplier_profile_id' => $supplier->id,

                'asset_type' => 'hosting_server',

                'asset_key' => 'server',

                'name' => 'Server',

                'observed_cost' => 100,

                'active' => true,
            ]);

        $service = app(
            CreateManagedService::class
        )->execute(
            client: $client,
            type: 'managed_hosting',
            name: 'Managed Hosting',
            expectedMonthlyRevenue: 90
        );

        app(
            LinkManagedServiceAsset::class
        )->execute(
            service: $service,
            asset: $server,
            role: 'primary',
            verified: true
        );

        $truth = app(
            ManagedServiceTruthService::class
        )->summary(
            $service
        );

        $this->assertSame(
            -10.0,
            $truth['monthly_margin']
        );

        $this->assertSame(
            20.0,
            $truth['completeness_score']
        );

        $this->assertSame(
            'LOW',
            $truth[
                'financial_confidence'
            ]
        );

        $this->assertSame(
            'PROVISIONAL',
            $truth['margin_status']
        );
    }
}
