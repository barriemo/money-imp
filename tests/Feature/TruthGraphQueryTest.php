<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CommercialAgreementAssertionService;
use App\Domains\TruthGraph\TruthGraphBuilder;
use App\Domains\TruthGraph\TruthGraphQuery;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruthGraphQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_can_query_canonical_commercial_agreements_for_client(): void
    {
        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Website Hosting',

                'type' => 'service',

                'status' => 'active',
            ]);

        $reviewer =
            User::factory()->create();

        app(
            CommercialAgreementAssertionService::class
        )->confirm(
            clientServiceId: $service->id,

            cadence: 'monthly',

            contractedAmountPence: 7500,

            effectiveFrom: CarbonImmutable::parse(
                '2026-01-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'test',

            reason: 'Truth graph query test contract.'
        );

        $graph =
            app(
                TruthGraphBuilder::class
            )->buildForClient(
                $client
            );

        $agreements =
            app(
                TruthGraphQuery::class
            )->nodesOfType(
                $graph,
                'commercial_agreement'
            );

        $this->assertCount(
            1,
            $agreements
        );

        $agreement =
            $agreements->first();

        $this->assertSame(
            $service->id,
            $agreement->attributes[
                'client_service_id'
            ]
        );

        $this->assertSame(
            'Website Hosting',
            $agreement->attributes[
                'client_service_name'
            ]
        );

        $this->assertSame(
            'monthly',
            $agreement->attributes[
                'cadence'
            ]
        );

        $this->assertSame(
            7500,
            $agreement->attributes[
                'contracted_amount_pence'
            ]
        );

        $this->assertSame(
            75.0,
            $agreement->attributes[
                'monthly_equivalent'
            ]
        );
    }
}
