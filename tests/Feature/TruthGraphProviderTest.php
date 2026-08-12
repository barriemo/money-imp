<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Graph\CommercialTruthGraphProvider;
use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Models\Client;
use App\Models\CommercialAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruthGraphProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_truth_contributes_to_client_graph(): void
    {
        $client =
            Client::factory()->create();

        $agreement =
            CommercialAgreement::create([
                'client_id' => $client->id,

                'service_type' => 'hosting',

                'service_key' => 'hosting',

                'cadence' => 'monthly',

                'status' => 'confirmed',

                'observed_value' => 75,

                'monthly_equivalent' => 75,

                'confidence' => 100,

                'source' => 'test',
            ]);

        $provider = app(
            CommercialTruthGraphProvider::class
        );

        $this->assertInstanceOf(
            TruthGraphProvider::class,
            $provider
        );

        $this->assertTrue(
            $provider->supports(
                'client'
            )
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
            1,
            $contribution->edges
        );

        $this->assertSame(
            'commercial_agreement:'
            .$agreement->id,
            $contribution
                ->nodes
                ->first()
                ->key()
        );

        $this->assertSame(
            'has_agreement',
            $contribution
                ->edges
                ->first()
                ->relationship
        );
    }

    public function test_provider_ignores_unsupported_root_type(): void
    {
        $provider = app(
            CommercialTruthGraphProvider::class
        );

        $this->assertFalse(
            $provider->supports(
                'supplier_asset'
            )
        );
    }
}
