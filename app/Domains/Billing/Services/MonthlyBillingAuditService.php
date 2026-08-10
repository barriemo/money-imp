<?php

namespace App\Domains\Billing\Services;

use App\Models\AccountingInvoice;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonthlyBillingAuditService
{
    public function audit(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $historyStart = $start->subMonths(4)->startOfMonth();
        $historyEnd = $start->subMonth()->endOfMonth();

        $clients = Client::query()
            ->where('status', 'active')
            ->with([
                'invoices' => fn ($query) => $query
                    ->whereBetween('invoice_date', [
                        $historyStart->toDateString(),
                        $end->toDateString(),
                    ])
                    ->whereNotIn('status', [
                        'refunded',
                        'written_off',
                        'zero_value',
                    ])
                    ->orderBy('invoice_date'),
            ])
            ->get();

        $rows = $clients
            ->map(fn (Client $client) => $this->analyseClient(
                $client,
                $start,
                $end,
                $historyStart,
                $historyEnd
            ))
            ->filter()
            ->values();

        return [
            'month' => $start,
            'rows' => $rows
                ->sortBy(function (array $row) {
                    return match ($row['status']) {
                        'missing' => 0,
                        'underbilled' => 1,
                        default => 2,
                    };
                })
                ->values(),

            'summary' => [
                'expected_clients' => $rows->count(),

                'issued' => $rows
                    ->where('status', 'issued')
                    ->count(),

                'drafts' => $rows
                    ->where('status', 'draft')
                    ->count(),

                'missing' => $rows
                    ->where('status', 'missing')
                    ->count(),

                'underbilled' => $rows
                    ->where('status', 'underbilled')
                    ->count(),

                'expected_value' => (float) $rows
                    ->sum('expected_amount'),

                'actual_value' => (float) $rows
                    ->sum('actual_amount'),

                'potential_missing_value' => (float) $rows
                    ->sum('potential_missing_amount'),
            ],
        ];
    }

    private function analyseClient(
        Client $client,
        CarbonImmutable $targetStart,
        CarbonImmutable $targetEnd,
        CarbonImmutable $historyStart,
        CarbonImmutable $historyEnd
    ): ?array {
        $history = $client->invoices
            ->filter(function (AccountingInvoice $invoice) use (
                $historyStart,
                $historyEnd
            ) {
                return $invoice->invoice_date
                    && $invoice->invoice_date->between(
                        $historyStart,
                        $historyEnd
                    );
            });

        $monthly = $history
            ->groupBy(
                fn (AccountingInvoice $invoice) => $invoice->invoice_date->format('Y-m')
            )
            ->map(
                fn (Collection $invoices) => (float) $invoices->sum('gross_amount')
            );

        if ($monthly->count() < 3) {
            return null;
        }

        $expected = $this->median($monthly->values());

        if ($expected <= 0) {
            return null;
        }

        $targetInvoices = $client->invoices
            ->filter(function (AccountingInvoice $invoice) use (
                $targetStart,
                $targetEnd
            ) {
                return $invoice->invoice_date
                    && $invoice->invoice_date->between(
                        $targetStart,
                        $targetEnd
                    );
            });

        $actual = (float) $targetInvoices->sum('gross_amount');

        $hasDraft = $targetInvoices->contains(
            fn (AccountingInvoice $invoice) => $invoice->status === 'draft'
        );

        $status = match (true) {
            $actual <= 0 => 'missing',
            $hasDraft => 'draft',
            $actual < ($expected * 0.80) => 'underbilled',
            default => 'issued',
        };

        return [
            'client' => $client,
            'history_months' => $monthly->count(),
            'history' => $monthly,
            'expected_amount' => round($expected, 2),
            'actual_amount' => round($actual, 2),
            'potential_missing_amount' => round(
                max(0, $expected - $actual),
                2
            ),
            'status' => $status,
            'target_invoices' => $targetInvoices->values(),
            'last_invoice' => $history
                ->sortByDesc('invoice_date')
                ->first(),
        ];
    }

    private function median(Collection $values): float
    {
        $values = $values
            ->map(fn ($value) => (float) $value)
            ->sort()
            ->values();

        $count = $values->count();

        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return (
            (float) $values[$middle - 1]
            + (float) $values[$middle]
        ) / 2;
    }
}
