<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Services\InfrastructureRecoveryService;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_billable_client_asset_without_charge_is_flagged(): void
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
            'asset_key' => 'server-1',
            'name' => 'Server 1',
            'observed_cost' => 152.96,
            'client_id' => $client->id,
            'purpose' => 'client',
            'billable' => true,
            'expected_charge' => null,
            'active' => true,
        ]);

        $summary = app(
            InfrastructureRecoveryService::class
        )->summary();

        $this->assertSame(
            152.96,
            $summary['client_cost']
        );

        $this->assertSame(
            0.0,
            $summary['expected_recovery']
        );

        $this->assertSame(
            152.96,
            $summary['recovery_gap']
        );

        $this->assertCount(
            1,
            $summary['missing_charge_assets']
        );
    }
}
