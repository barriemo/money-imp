<?php

namespace App\Domains\BusinessBrain\Interrogation\Coverage;

use App\Models\Client;

class BusinessCoverageSummaryService
{
    public function __construct(
        private BusinessTruthCoverageService $coverage
    ) {}

    public function current(): BusinessCoverageSummary
    {
        $clients =
            Client::query()
                ->where(
                    'status',
                    'active'
                )
                ->get();

        $coverages =
            $clients
                ->map(
                    fn (Client $client) => $this->coverage
                        ->forClient(
                            $client
                        )
                );

        return new BusinessCoverageSummary(
            clientCount: $clients->count(),

            clientsWithoutInvoices: $coverages
                ->where(
                    'hasInvoices',
                    false
                )
                ->count(),

            clientsWithoutBankTransactions: $coverages
                ->where(
                    'hasBankTransactions',
                    false
                )
                ->count(),

            clientsWithoutPaymentIdentities: $coverages
                ->where(
                    'hasPaymentIdentity',
                    false
                )
                ->count(),

            clientsWithoutWorkLogs: $coverages
                ->where(
                    'hasWorkLogs',
                    false
                )
                ->count(),

            clientsWithoutServices: $coverages
                ->where(
                    'hasServices',
                    false
                )
                ->count(),

            clientsWithoutCharlieFindings: $coverages
                ->where(
                    'hasCharlieFindings',
                    false
                )
                ->count(),

            averageCoverageConfidence: $coverages->isEmpty()
                ? 0
                : (int) round(
                    $coverages->avg(
                        'confidence'
                    )
                )
        );
    }
}
