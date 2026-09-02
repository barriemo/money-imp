<?php

namespace App\Domains\CommercialTruth;

use App\Models\CommercialAgreement;

class CommercialAgreementTruthService
{
    /**
     * Summarise persisted commercial agreement assertions.
     *
     * This method is deliberately read only.
     *
     * Invoice-derived candidates are not contracted truth and are
     * never created merely because this summary was requested.
     */
    public function summary(): array
    {
        $agreements =
            CommercialAgreement::query()
                ->with('evidence')
                ->get();

        $confirmed =
            $agreements
                ->where(
                    'status',
                    'confirmed'
                )
                ->values();

        $candidates =
            $agreements
                ->where(
                    'status',
                    'candidate'
                )
                ->values();

        $monthly =
            $confirmed
                ->where(
                    'cadence',
                    'monthly'
                );

        $annual =
            $confirmed
                ->where(
                    'cadence',
                    'annual'
                );

        $oneOff =
            $confirmed
                ->where(
                    'cadence',
                    'one_off'
                );

        $unknown =
            $confirmed
                ->where(
                    'cadence',
                    'unknown'
                );

        $recurringMonthlyEquivalent =
            round(
                (float) $confirmed
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

        $contractedValueStatus =
            match (true) {
                $confirmed->isEmpty()
                    && $candidates->isEmpty() => 'not_established',

                $confirmed->isEmpty() => 'candidates_not_confirmed',

                $candidates->isNotEmpty() => 'partially_reconciled',

                default => 'reconciled',
            };

        return [
            'agreements' => $agreements,

            'confirmed_agreements' => $confirmed,

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

            'candidate_count' => $candidates->count(),

            'confirmed_count' => $confirmed->count(),

            'contracted_monthly_value' => $confirmed->isEmpty()
                    ? null
                    : $recurringMonthlyEquivalent,

            'contracted_value_status' => $contractedValueStatus,
        ];
    }
}
