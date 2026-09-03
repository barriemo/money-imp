<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CommercialAgreementAssertionService;
use App\Domains\TruthGraph\TruthGraphBuilder;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruthGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_graph_contains_canonical_commercial_agreement_assertion(): void
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

        $agreement =
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

                reason: 'Truth graph integration test contract.'
            );

        $graph =
            app(
                TruthGraphBuilder::class
            )->buildForClient(
                $client
            );

        $this->assertSame(
            'client:'
            .$client->id,
            $graph['root']
        );

        $this->assertTrue(
            $graph['nodes']
                ->contains(
                    fn ($node) => $node->key()
                        === 'commercial_agreement:'
                        .$agreement->id
                )
        );

        $this->assertTrue(
            $graph['edges']
                ->contains(
                    fn ($edge) => $edge->relationship
                            === 'has_agreement'
                        && $edge->to
                            === 'commercial_agreement:'
                            .$agreement->id
                )
        );

        $agreementNode =
            $graph['nodes']
                ->first(
                    fn ($node) => $node->key()
                        === 'commercial_agreement:'
                        .$agreement->id
                );

        $this->assertNotNull(
            $agreementNode
        );

        $this->assertSame(
            $service->id,
            $agreementNode
                ->attributes[
                    'client_service_id'
                ]
        );

        $this->assertSame(
            7500,
            $agreementNode
                ->attributes[
                    'contracted_amount_pence'
                ]
        );
    }
}
