<?php

namespace App\Domains\Suppliers\Services;

use Illuminate\Support\Collection;

class RecurringCostDetector
{
    public function detect(
        Collection $transactions
    ): bool {
        if ($transactions->count() < 3) {
            return false;
        }

        $months = $transactions
            ->pluck('transaction_date')
            ->filter()
            ->map(
                fn ($date) => $date->format('Y-m')
            )
            ->unique();

        return $months->count() >= 3;
    }
}
