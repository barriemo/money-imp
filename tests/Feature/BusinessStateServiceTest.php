<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessStateGaps;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGapService;
use App\Domains\BusinessBrain\BusinessState\BusinessStateService;
use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruth;
use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruthService;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPositionService;
use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessTruthCoverage;
use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessTruthCoverageService;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummaryService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BusinessStateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_composes_authoritative_truth_for_active_clients(): void
    {
        $active =
            Client::factory()->create([
                'name' => 'Active State Client',
                'status' => 'active',
            ]);

        Client::factory()->create([
            'name' => 'Inactive State Client',
            'status' => 'inactive',
        ]);

        $financial =
            Mockery::mock(
                FinancialPosition::class
            );

        $revenue =
            Mockery::mock(
                RevenueTruthSummary::class
            );

        $delivery =
            new DeliveryTruth(
                clientId: (string) $active->id,

                client: $active->name,

                workLogCount: 0,

                invoicedWorkLogCount: 0,

                uninvoicedWorkLogCount: 0,

                commercialValue: 0,

                invoicedCommercialValue: 0,

                uninvoicedCommercialValue: 0,

                invoiceLinkageConfidence: 0
            );

        $coverage =
            new BusinessTruthCoverage(
                client: $active->name,

                invoiceCount: 0,

                bankTransactionCount: 0,

                paymentIdentityCount: 0,

                workLogCount: 0,

                serviceCount: 0,

                openCharlieFindingCount: 0,

                hasInvoices: false,

                hasBankTransactions: false,

                hasPaymentIdentity: false,

                hasWorkLogs: false,

                hasServices: false,

                hasCharlieFindings: false,

                confidence: 0
            );

        $financialService =
            Mockery::mock(
                FinancialPositionService::class
            );

        $financialService
            ->shouldReceive(
                'current'
            )
            ->once()
            ->andReturn(
                $financial
            );

        $revenueService =
            Mockery::mock(
                RevenueTruthSummaryService::class
            );

        $revenueService
            ->shouldReceive(
                'current'
            )
            ->once()
            ->andReturn(
                $revenue
            );

        $deliveryService =
            Mockery::mock(
                DeliveryTruthService::class
            );

        $deliveryService
            ->shouldReceive(
                'forClient'
            )
            ->once()
            ->withArgs(
                fn (Client $client) => $client->is(
                    $active
                )
            )
            ->andReturn(
                $delivery
            );

        $coverageService =
            Mockery::mock(
                BusinessTruthCoverageService::class
            );

        $coverageService
            ->shouldReceive(
                'forClient'
            )
            ->once()
            ->withArgs(
                fn (Client $client) => $client->is(
                    $active
                )
            )
            ->andReturn(
                $coverage
            );

        $gaps =
            new BusinessStateGaps(
                unknowns: collect(),

                evidenceGaps: collect()
            );

        $gapService =
            Mockery::mock(
                BusinessStateGapService::class
            );

        $gapService
            ->shouldReceive(
                'assess'
            )
            ->once()
            ->withArgs(
                fn (
                    FinancialPosition $position,
                    $clients
                ) => (
                    $position === $financial
                    && $clients->count() === 1
                    && $clients
                        ->first()
                        ->clientId === (string) $active->id
                )
            )
            ->andReturn(
                $gaps
            );

        $state =
            (
                new BusinessStateService(
                    financial: $financialService,

                    revenue: $revenueService,

                    delivery: $deliveryService,

                    coverage: $coverageService,

                    gaps: $gapService
                )
            )->current();

        $this->assertSame(
            $financial,
            $state->financial
        );

        $this->assertSame(
            $revenue,
            $state->revenue
        );

        $this->assertCount(
            1,
            $state->clients
        );

        $clientState =
            $state->clients
                ->first();

        $this->assertSame(
            (string) $active->id,
            $clientState->clientId
        );

        $this->assertSame(
            'Active State Client',
            $clientState->client
        );

        $this->assertSame(
            $delivery,
            $clientState->delivery
        );

        $this->assertSame(
            $coverage,
            $clientState->coverage
        );

        $this->assertSame(
            $gaps,
            $state->gaps
        );

        /*
         * Business State composes authoritative confidence domains.
         *
         * It must not invent a blended company confidence score from
         * fundamentally different measures such as financial confidence,
         * commercial confidence and evidence-presence coverage.
         */
        $this->assertFalse(
            property_exists(
                $state,
                'confidence'
            )
        );
    }
}
