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

        $latestValue =
            (float) (
                $observations
                    ->last()
                    ->unit_price
                ?? 0
            );

        if ($observations->count() === 1) {
            return $this->result(
                cadence: 'one_off',
                confidence: 70,
                observedValue: $latestValue
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
                observedValue: $latestValue
            );
        }

        if (
            $averageDays >= 300
            && $averageDays <= 430
        ) {
            return $this->result(
                cadence: 'annual',
                confidence: min(
                    99,
                    85
                    + ($observations->count() * 2)
                ),
                observedValue: $latestValue
            );
        }

        return $this->result(
            cadence: 'unknown',
            confidence: 60,
            observedValue: $latestValue
        );
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
