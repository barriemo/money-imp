<?php

namespace App\Domains\Suppliers\Services;

use App\Domains\Suppliers\DTOs\SupplierAnalysis;
use App\Models\BankTransaction;
use App\Models\CostAllocation;
use App\Models\SupplierProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupplierAnalysisService
{
    public function __construct(
        private RecurringCostDetector $recurringCosts
    ) {}

    public function analyseAll(): Collection
    {
        return SupplierProfile::query()
            ->where('active', true)
            ->orderBy('supplier_name')
            ->get()
            ->map(
                fn (SupplierProfile $supplier) => $this->analyse($supplier)
            );
    }

    public function analyse(
        SupplierProfile $supplier
    ): SupplierAnalysis {
        $transactions = $this->transactionsFor(
            $supplier
        );

        $totalSpend = abs(
            (float) $transactions->sum('amount')
        );

        $last30DaysSpend = abs(
            (float) $transactions
                ->filter(
                    fn (BankTransaction $transaction) => $transaction->transaction_date
                        && $transaction->transaction_date
                            ->gte(now()->subDays(30))
                )
                ->sum('amount')
        );

        $months = $transactions
            ->pluck('transaction_date')
            ->filter()
            ->map(
                fn ($date) => $date->format('Y-m')
            )
            ->unique()
            ->count();

        $averageMonthly = $months > 0
            ? $totalSpend / $months
            : 0.0;

        $allocatedSpend =
            $this->allocatedSpend(
                $transactions
            );

        return new SupplierAnalysis(
            supplier: $supplier,

            transactionCount: $transactions->count(),

            totalSpend: round($totalSpend, 2),

            last30DaysSpend: round($last30DaysSpend, 2),

            averageMonthlySpend: round($averageMonthly, 2),

            annualisedSpend: round($averageMonthly * 12, 2),

            allocatedSpend: round($allocatedSpend, 2),

            unallocatedSpend: round(
                max(
                    0,
                    $totalSpend - $allocatedSpend
                ),
                2
            ),

            recurring: $this->recurringCosts
                ->detect($transactions),

            lastSeen: $transactions
                ->max('transaction_date')
                ?->toDateString(),
        );
    }

    private function transactionsFor(
        SupplierProfile $supplier
    ): Collection {
        $key = $this->normalise(
            $supplier->supplier_key
        );

        return BankTransaction::query()
            ->where('amount', '<', 0)
            ->get()
            ->filter(
                function (
                    BankTransaction $transaction
                ) use ($key): bool {
                    $haystack = $this->normalise(
                        implode(' ', [
                            $transaction->description
                                ?? '',
                            $transaction->reference
                                ?? '',
                            data_get(
                                $transaction->metadata,
                                'merchant',
                                ''
                            ),
                            data_get(
                                $transaction->raw_payload,
                                'merchant',
                                ''
                            ),
                        ])
                    );

                    return $key !== ''
                        && Str::contains(
                            $haystack,
                            $key
                        );
                }
            )
            ->values();
    }

    private function allocatedSpend(
        Collection $transactions
    ): float {
        if ($transactions->isEmpty()) {
            return 0.0;
        }

        return (float) CostAllocation::query()
            ->where(
                'cost_allocatable_type',
                BankTransaction::class
            )
            ->whereIn(
                'cost_allocatable_id',
                $transactions->pluck('id')
            )
            ->sum('amount');
    }

    private function normalise(
        ?string $value
    ): string {
        return Str::of(
            $value ?? ''
        )
            ->lower()
            ->replaceMatches(
                '/[^a-z0-9]+/',
                ' '
            )
            ->squish()
            ->value();
    }
}
