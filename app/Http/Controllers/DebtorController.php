<?php

namespace App\Http\Controllers;

use App\Domains\Accounting\Services\InvoiceBalanceService;
use App\Models\AccountingInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DebtorController extends Controller
{
    public function index(
        Request $request,
        InvoiceBalanceService $balances
    ): View {
        $invoices = AccountingInvoice::query()
            ->with([
                'client',
                'paymentAllocations',
            ])
            ->whereNotNull('client_id')
            ->whereNotIn('status', [
                'draft',
                'paid',
                'refunded',
                'written_off',
                'zero_value',
            ])
            ->orderBy('due_date')
            ->get()
            ->map(function (AccountingInvoice $invoice) use ($balances) {
                $operationalOutstanding = (float) $balances->outstanding(
                    $invoice
                );

                if ($operationalOutstanding <= 0) {
                    return null;
                }

                $daysOverdue = 0;

                if ($invoice->due_date && $invoice->due_date->isPast()) {
                    $daysOverdue = $invoice->due_date
                        ->startOfDay()
                        ->diffInDays(now()->startOfDay());
                }

                return [
                    'invoice' => $invoice,
                    'client' => $invoice->client,
                    'outstanding' => $operationalOutstanding,
                    'days_overdue' => $daysOverdue,
                    'is_overdue' => $daysOverdue > 0,
                    'age_band' => $this->ageBand($daysOverdue),
                ];
            })
            ->filter()
            ->values();

        $clients = $invoices
            ->groupBy(fn (array $row) => $row['client']->id)
            ->map(function (Collection $rows) {
                $first = $rows->first();

                $totalOutstanding = (float) $rows->sum('outstanding');
                $oldestDaysOverdue = (int) $rows->max('days_overdue');

                return [
                    'client' => $first['client'],
                    'total_outstanding' => $totalOutstanding,
                    'invoice_count' => $rows->count(),
                    'overdue_count' => $rows
                        ->where('is_overdue', true)
                        ->count(),
                    'oldest_days_overdue' => $oldestDaysOverdue,
                    'chase_score' => $this->chaseScore(
                        $totalOutstanding,
                        $oldestDaysOverdue,
                        $rows->where('is_overdue', true)->count()
                    ),
                    'invoices' => $rows
                        ->sortByDesc('days_overdue')
                        ->values(),
                ];
            })
            ->sortByDesc('chase_score')
            ->values();

        return view('debtors.index', [
            'clients' => $clients,
            'summary' => [
                'clients' => $clients->count(),
                'invoices' => $invoices->count(),
                'total' => (float) $invoices->sum('outstanding'),
                'overdue_total' => (float) $invoices
                    ->where('is_overdue', true)
                    ->sum('outstanding'),
                'overdue_invoices' => $invoices
                    ->where('is_overdue', true)
                    ->count(),
                'bands' => [
                    'current' => $this->bandTotal($invoices, 'current'),
                    '1_30' => $this->bandTotal($invoices, '1_30'),
                    '31_60' => $this->bandTotal($invoices, '31_60'),
                    '61_90' => $this->bandTotal($invoices, '61_90'),
                    '90_plus' => $this->bandTotal($invoices, '90_plus'),
                ],
            ],
        ]);
    }

    private function ageBand(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => '1_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => '90_plus',
        };
    }

    private function bandTotal(
        Collection $invoices,
        string $band
    ): float {
        return (float) $invoices
            ->where('age_band', $band)
            ->sum('outstanding');
    }

    private function chaseScore(
        float $outstanding,
        int $oldestDaysOverdue,
        int $overdueCount
    ): float {
        return
            ($outstanding / 100)
            + ($oldestDaysOverdue * 2)
            + ($overdueCount * 10);
    }
}
