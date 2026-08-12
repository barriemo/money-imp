<?php

namespace Tests\Feature;

use App\Domains\TruthGraph\TruthGraphBuilder;
use App\Models\Client;
use App\Models\CommercialAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruthGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_graph_contains_commercial_agreement(): void
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

        $graph = app(
            TruthGraphBuilder::class
        )->buildForClient(
            $client
        );

        $this->assertSame(
            'client:'
            .$client->id,
            $graph['root']
        );

        $this->assertCount(
            2,
            $graph['nodes']
        );

        $this->assertCount(
            1,
            $graph['edges']
        );

        $edge =
            $graph['edges']
                ->first();

        $this->assertSame(
            'has_agreement',
            $edge->relationship
        );

        $this->assertSame(
            'commercial_agreement:'
            .$agreement->id,
            $edge->to
        );
    }
}
