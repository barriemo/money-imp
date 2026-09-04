<?php

namespace App\Domains\BusinessBrain\BusinessState;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruthService;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPositionService;
use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessTruthCoverageService;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummaryService;
use App\Models\Client;
use Carbon\CarbonImmutable;

class BusinessStateService
{
    public function __construct(
        private FinancialPositionService $financial,

        private RevenueTruthSummaryService $revenue,

        private DeliveryTruthService $delivery,

        private BusinessTruthCoverageService $coverage
    ) {}

    public function current(): BusinessState
    {
        $financial =
            $this->financial
                ->current();

        $revenue =
            $this->revenue
                ->current();

        $clients =
            Client::query()
                ->where(
                    'status',
                    'active'
                )
                ->orderBy(
                    'id'
                )
                ->get()
                ->map(
                    fn (Client $client) => new ClientState(
                        clientId: (string) $client->id,

                        client: $client->name,

                        delivery: $this->delivery
                            ->forClient(
                                $client
                            ),

                        coverage: $this->coverage
                            ->forClient(
                                $client
                            )
                    )
                )
                ->values();

        return new BusinessState(
            financial: $financial,

            revenue: $revenue,

            clients: $clients,

            asOf: CarbonImmutable::now()
        );
    }
}
