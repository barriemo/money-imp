<?php

namespace App\Domains\CommercialTruth\Recovery;

use App\Models\Client;

class RecoveryOpportunitySummariser
{
    public function __construct(
        private RecoveryOpportunityFinder $finder
    ) {}

    public function summarise(
        Client $client
    ): RecoveryOpportunitySummary {
        $opportunities =
            $this->finder->find(
                $client
            );

        $totalValue =
            round(
                (float) $opportunities->sum(
                    fn (RecoveryOpportunity $opportunity) => $opportunity->value
                ),
                2
            );

        $highestValue =
            round(
                (float) (
                    $opportunities->max(
                        fn (RecoveryOpportunity $opportunity) => $opportunity->value
                    )
                    ?? 0
                ),
                2
            );

        $confidence =
            $opportunities->isEmpty()
                ? 0
                : (int) round(
                    $opportunities->avg(
                        fn (RecoveryOpportunity $opportunity) => $opportunity->confidence
                    )
                );

        return new RecoveryOpportunitySummary(
            clientId: $client->id,
            opportunityCount: $opportunities->count(),
            totalValue: $totalValue,
            highestValue: $highestValue,
            confidence: $confidence
        );
    }
}
