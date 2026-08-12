<?php

namespace Tests\Feature;

use App\Domains\TruthGraph\TruthGraphBuilder;
use App\Domains\TruthGraph\TruthGraphQuery;
use App\Models\Client;
use App\Models\CommercialAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruthGraphQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_can_query_commercial_agreements_for_client(): void
    {
        $client =
            Client::factory()->create();

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

        $agreements = app(
            TruthGraphQuery::class
        )->nodesOfType(
            $graph,
            'commercial_agreement'
        );

        $this->assertCount(
            1,
            $agreements
        );

        $this->assertSame(
            'hosting',
            $agreements
                ->first()
                ->attributes[
                    'service_type'
                ]
        );
    }
}
