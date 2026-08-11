<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Attribution\HostingServerResolver;
use App\Domains\ManagedServices\Actions\LinkManagedServiceAsset;
use App\Models\AttributionCandidate;
use App\Models\Client;
use App\Models\ManagedService;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostingServerResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_managed_hosting_server_resolves_client_candidate(): void
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

        $candidate =
            AttributionCandidate::create([
                'fingerprint' => hash(
                    'sha256',
                    'client|'
                    .$client->id
                    .'|hosted_on|supplier_asset|unknown'
                ),

                'subject_type' => 'client',

                'subject_id' => $client->id,

                'relationship_type' => 'hosted_on',

                'target_type' => 'supplier_asset',

                'target_id' => null,

                'confidence' => 98,

                'status' => 'candidate',

                'source' => 'hosting_invoice_history',

                'reason' => 'Hosting billing exists but server is unknown.',
            ]);

        $resolved = app(
            HostingServerResolver::class
        )->resolveCandidate(
            $candidate
        );

        $this->assertNotNull(
            $resolved
        );

        $this->assertSame(
            $server->id,
            $resolved->target_id
        );

        $this->assertSame(
            100,
            $resolved->confidence
        );

        $this->assertSame(
            'confirmed',
            $resolved->status
        );
    }

    public function test_multiple_possible_servers_remain_unresolved(): void
    {
        $client =
            Client::factory()->create();

        $candidate =
            AttributionCandidate::create([
                'fingerprint' => hash(
                    'sha256',
                    'client|'
                    .$client->id
                    .'|hosted_on|supplier_asset|unknown'
                ),

                'subject_type' => 'client',

                'subject_id' => $client->id,

                'relationship_type' => 'hosted_on',

                'target_type' => 'supplier_asset',

                'target_id' => null,

                'confidence' => 98,

                'status' => 'candidate',

                'source' => 'hosting_invoice_history',
            ]);

        $resolved = app(
            HostingServerResolver::class
        )->resolveCandidate(
            $candidate
        );

        $this->assertNull(
            $resolved
        );

        $this->assertNull(
            $candidate
                ->fresh()
                ->target_id
        );
    }
}
