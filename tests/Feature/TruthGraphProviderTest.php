<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Graph\CommercialTruthGraphProvider;
use App\Domains\CommercialTruth\Services\CommercialAgreementAssertionService;
use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruthGraphProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_truth_contributes_canonical_contract_assertion_to_client_graph(): void
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
            User::factory()->create([
                'name' => 'Commercial Reviewer',
            ]);

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

                reason: 'Truth graph test contract.'
            );

        $provider =
            app(
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

        $node =
            $contribution
                ->nodes
                ->first();

        $this->assertSame(
            'commercial_agreement:'
            .$agreement->id,
            $node->key()
        );

        $this->assertSame(
            'Website Hosting agreement',
            $node->label
        );

        $this->assertSame(
            $service->id,
            $node->attributes[
                'client_service_id'
            ]
        );

        $this->assertSame(
            'Website Hosting',
            $node->attributes[
                'client_service_name'
            ]
        );

        $this->assertSame(
            7500,
            $node->attributes[
                'contracted_amount_pence'
            ]
        );

        $this->assertSame(
            75.0,
            $node->attributes[
                'monthly_equivalent'
            ]
        );

        $this->assertSame(
            100,
            $node->confidence
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
        $provider =
            app(
                CommercialTruthGraphProvider::class
            );

        $this->assertFalse(
            $provider->supports(
                'supplier_asset'
            )
        );
    }
}
