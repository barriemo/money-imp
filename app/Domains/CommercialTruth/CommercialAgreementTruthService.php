<?php

namespace App\Domains\CommercialTruth;

class CommercialAgreementTruthService
{
    public function __construct(
        private CommercialAgreementInferenceService $inference
    ) {}

    public function summary(): array
    {
        $agreements =
            $this->inference
                ->inferHosting();

        $monthly =
            $agreements
                ->where(
                    'cadence',
                    'monthly'
                );

        $annual =
            $agreements
                ->where(
                    'cadence',
                    'annual'
                );

        $oneOff =
            $agreements
                ->where(
                    'cadence',
                    'one_off'
                );

        $unknown =
            $agreements
                ->where(
                    'cadence',
                    'unknown'
                );

        $recurringMonthlyEquivalent =
            round(
                (float) $agreements
                    ->whereIn(
                        'cadence',
                        [
                            'monthly',
                            'annual',
                        ]
                    )
                    ->sum(
                        'monthly_equivalent'
                    ),
                2
            );

        return [
            'agreements' => $agreements,

            'agreement_count' => $agreements->count(),

            'monthly_count' => $monthly->count(),

            'annual_count' => $annual->count(),

            'one_off_count' => $oneOff->count(),

            'unknown_count' => $unknown->count(),

            'recurring_monthly_equivalent' => $recurringMonthlyEquivalent,

            'recurring_annual_equivalent' => round(
                $recurringMonthlyEquivalent
                * 12,
                2
            ),

            'candidate_count' => $agreements
                ->where(
                    'status',
                    'candidate'
                )
                ->count(),

            'confirmed_count' => $agreements
                ->where(
                    'status',
                    'confirmed'
                )
                ->count(),
        ];
    }
}
