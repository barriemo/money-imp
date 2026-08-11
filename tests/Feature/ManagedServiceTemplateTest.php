<?php

namespace Tests\Feature;

use App\Domains\ManagedServices\Actions\CreateManagedService;
use App\Domains\ManagedServices\Actions\LinkManagedServiceAsset;
use App\Domains\ManagedServices\Templates\ManagedServiceTemplateEvaluator;
use App\Models\Client;
use App\Models\ManagedServiceRequirement;
use App\Models\ManagedServiceTemplate;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedServiceTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_hosting_completeness_is_evaluated(): void
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
            [
                'type' => 'hosting_server',
                'name' => 'Hosting Server',
                'required' => true,
            ],
            [
                'type' => 'control_panel',
                'name' => 'Control Panel',
                'required' => true,
            ],
            [
                'type' => 'backup',
                'name' => 'Backup',
                'required' => true,
            ],
            [
                'type' => 'ssl',
                'name' => 'SSL',
                'required' => true,
            ],
            [
                'type' => 'monitoring',
                'name' => 'Monitoring',
                'required' => false,
            ],
        ] as $item) {
            ManagedServiceRequirement::create([
                'managed_service_template_id' => $template->id,

                'component_type' => $item['type'],

                'name' => $item['name'],

                'required' => $item['required'],

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

        $result = app(
            ManagedServiceTemplateEvaluator::class
        )->evaluate(
            $service
        );

        $this->assertSame(
            40.0,
            $result->score
        );

        $this->assertCount(
            2,
            $result->present
        );

        $this->assertCount(
            2,
            $result->missing
        );

        $this->assertCount(
            1,
            $result->recommendedMissing
        );

        $this->assertFalse(
            $result->complete()
        );
    }
}
