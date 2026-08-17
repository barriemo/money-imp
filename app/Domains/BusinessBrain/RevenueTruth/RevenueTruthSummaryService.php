<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

use App\Models\Client;

class RevenueTruthSummaryService
{
    public function __construct(
        private RevenueTruthService $truth,

        private CommercialGapDetector $gaps
    ) {}

    public function current(): RevenueTruthSummary
    {
        $clients =
            Client::query()
                ->where(
                    'status',
                    'active'
                )
                ->get();

        $truths =
            $clients
                ->map(
                    fn (Client $client) => $this->truth
                        ->forClient(
                            $client
                        )
                );

        $gaps =
            $truths
                ->flatMap(
                    fn (RevenueTruth $truth) => $this->gaps
                        ->detect(
                            $truth
                        )
                )
                ->sortByDesc(
                    'priority'
                )
                ->values();

        return new RevenueTruthSummary(
            clientCount: $clients->count(),

            grossInvoiced: (float) $truths
                ->sum(
                    'grossInvoiced'
                ),

            paidAccordingToAccounting: (float) $truths
                ->sum(
                    'paidAccordingToAccounting'
                ),

            outstanding: (float) $truths
                ->sum(
                    'outstanding'
                ),

            unrecoveredWorkValue: (float) $truths
                ->sum(
                    'unrecoveredWorkValue'
                ),

            bankVerifiedPaymentValue: (float) $truths
                ->sum(
                    'bankVerifiedPaymentValue'
                ),

            clientsWithOutstandingRevenue: $truths
                ->where(
                    'outstanding',
                    '>',
                    0
                )
                ->count(),

            clientsWithWeakPaymentEvidence: $truths
                ->where(
                    'paymentEvidenceConfidence',
                    '<',
                    100
                )
                ->count(),

            clientsWithoutWorkEvidence: $truths
                ->where(
                    'workLogCount',
                    0
                )
                ->count(),

            averageCommercialConfidence: (int) round(
                $truths
                    ->avg(
                        'commercialConfidence'
                    ) ?? 0
            ),

            gaps: $gaps
        );
    }
}
