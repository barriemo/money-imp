<?php

namespace App\Domains\CommercialTruth;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class BillingCadenceEngine
{
    public function infer(
        Collection $observations
    ): array {
        $observations = $observations
            ->sortBy('invoice_date')
            ->values();

        if ($observations->isEmpty()) {
            return $this->result(
                cadence: 'unknown',
                confidence: 0,
                observedValue: 0
            );
        }

        $latest =
            $observations->last();

        $latestUnitPrice =
            (float) (
                $latest->unit_price
                ?? 0
            );

        $latestNetAmount =
            (float) (
                $latest->net_amount
                ?? $latestUnitPrice
            );

        if ($observations->count() === 1) {
            return $this->result(
                cadence: 'one_off',
                confidence: 70,
                observedValue: $latestNetAmount
            );
        }

        $intervals = collect();

        for (
            $index = 1;
            $index < $observations->count();
            $index++
        ) {
            $previous =
                Carbon::parse(
                    $observations[$index - 1]
                        ->invoice_date
                );

            $current =
                Carbon::parse(
                    $observations[$index]
                        ->invoice_date
                );

            $intervals->push(
                $previous->diffInDays(
                    $current
                )
            );
        }

        $averageDays =
            (float) $intervals->avg();

        if (
            $averageDays >= 20
            && $averageDays <= 45
        ) {
            return $this->result(
                cadence: 'monthly',
                confidence: min(
                    99,
                    80
                    + ($observations->count() * 2)
                ),
                observedValue: $this
                    ->monthlyObservedValue(
                        $observations
                    )
            );
        }

        if (
            $averageDays >= 300
            && $averageDays <= 430
        ) {
            /*
             * Annual recurring evidence represents the billed
             * annual line value.
             *
             * Quantity may represent multiple domains,
             * licences or other units. Using only unit_price
             * would understate those annual observations.
             */
            return $this->result(
                cadence: 'annual',
                confidence: min(
                    99,
                    85
                    + ($observations->count() * 2)
                ),
                observedValue: $latestNetAmount
            );
        }

        return $this->result(
            cadence: 'unknown',
            confidence: 60,
            observedValue: $latestNetAmount
        );
    }

    private function monthlyObservedValue(
        Collection $observations
    ): float {
        $latest =
            $observations->last();

        $latestUnitPrice =
            (float) (
                $latest->unit_price
                ?? 0
            );

        $latestNetAmount =
            (float) (
                $latest->net_amount
                ?? $latestUnitPrice
            );

        $latestQuantity =
            (float) (
                $latest->quantity
                ?? 1
            );

        /*
         * Normal quantity-one billing needs no normalisation.
         */
        if (
            abs(
                $latestNetAmount
                - $latestUnitPrice
            ) < 0.01
        ) {
            return $latestNetAmount;
        }

        /*
         * A stable multi-unit monthly service should preserve
         * the complete billed monthly line value.
         *
         * Example:
         * 3 licences x £20 every month = £60/month.
         *
         * But a temporary quantity spike can instead represent
         * catch-up billing after skipped invoice periods.
         *
         * We only normalise such a spike when:
         * - there are at least three prior observations;
         * - recent prior observations consistently have
         *   quantity 1;
         * - their unit price matches the latest unit price;
         * - the latest quantity matches the approximate number
         *   of months represented by the billing gap.
         */
        $prior =
            $observations
                ->slice(
                    0,
                    -1
                )
                ->take(-3)
                ->values();

        if ($prior->count() >= 3) {
            $stablePriorQuantity =
                $prior->every(
                    fn ($row) => abs(
                        (float) (
                            $row->quantity
                            ?? 1
                        )
                        - 1
                    ) < 0.01
                );

            $stablePriorUnitPrice =
                $prior->every(
                    fn ($row) => abs(
                        (float) (
                            $row->unit_price
                            ?? 0
                        )
                        - $latestUnitPrice
                    ) < 0.01
                );

            $previous =
                $prior->last();

            $days =
                Carbon::parse(
                    $previous->invoice_date
                )->diffInDays(
                    Carbon::parse(
                        $latest->invoice_date
                    )
                );

            $monthsRepresented =
                max(
                    1,
                    (int) round(
                        $days / 30.4375
                    )
                );

            $quantityMatchesGap =
                $monthsRepresented > 1
                && abs(
                    $latestQuantity
                    - $monthsRepresented
                ) < 0.01;

            if (
                $stablePriorQuantity
                && $stablePriorUnitPrice
                && $quantityMatchesGap
            ) {
                return $latestUnitPrice;
            }
        }

        return $latestNetAmount;
    }

    private function result(
        string $cadence,
        int $confidence,
        float $observedValue
    ): array {
        return [
            'cadence' => $cadence,

            'confidence' => $confidence,

            'observed_value' => round(
                $observedValue,
                2
            ),

            'monthly_equivalent' => match ($cadence) {
                'monthly' => round(
                    $observedValue,
                    2
                ),

                'annual' => round(
                    $observedValue / 12,
                    2
                ),

                default => 0.0,
            },
        ];
    }
}
