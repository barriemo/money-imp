<?php

namespace Tests\Feature;

use App\Domains\ManagedServices\Actions\CreateManagedService;
use App\Domains\ManagedServices\Actions\LinkManagedServiceAsset;
use App\Domains\OperationalTruth\Services\OperationalTruthService;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_truth_rolls_up_managed_service_financials(): void
    {
        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

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

                'asset_type' => 'hosting_server',

                'asset_key' => 'server-1',

                'name' => 'Server 1',

                'observed_cost' => 100,

                'client_id' => $client->id,

                'purpose' => 'client',

                'billable' => true,

                'active' => true,
            ]);

        $service = app(
            CreateManagedService::class
        )->execute(
            client: $client,
            type: 'managed_hosting',
            name: 'Managed Hosting',
            expectedMonthlyRevenue: 150
        );

        app(
            LinkManagedServiceAsset::class
        )->execute(
            service: $service,
            asset: $asset,
            role: 'primary',
            verified: true
        );

        $summary = app(
            OperationalTruthService::class
        )->summary();

        $this->assertSame(
            1,
            $summary[
                'managed_services'
            ]['count']
        );

        $this->assertSame(
            150.0,
            $summary[
                'managed_services'
            ]['monthly_revenue']
        );

        $this->assertSame(
            100.0,
            $summary[
                'managed_services'
            ]['monthly_cost']
        );

        $this->assertSame(
            50.0,
            $summary[
                'managed_services'
            ]['monthly_margin']
        );
    }
}
