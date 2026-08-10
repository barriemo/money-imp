<?php

namespace App\Http\Controllers;

use App\Domains\Accounting\Services\InvoiceBalanceService;
use App\Models\AccountingInvoice;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ChaseQueueController extends Controller
{
    public function index(
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
            ->get()
            ->map(function (AccountingInvoice $invoice) use ($balances) {
                $outstanding = (float) $balances->outstanding($invoice);

                if (
                    $outstanding <= 0
                    || ! $invoice->due_date
                    || ! $invoice->due_date->isPast()
                ) {
                    return null;
                }

                return [
                    'invoice' => $invoice,
                    'client' => $invoice->client,
                    'outstanding' => $outstanding,
                    'days_overdue' => (int) $invoice->due_date
                        ->startOfDay()
                        ->diffInDays(now()->startOfDay()),
                ];
            })
            ->filter()
            ->values();

        $clients = $invoices
            ->groupBy(fn (array $row) => $row['client']->id)
            ->map(function (Collection $rows) {
                $first = $rows->first();

                $total = (float) $rows->sum('outstanding');
                $oldest = (int) $rows->max('days_overdue');

                return [
                    'client' => $first['client'],
                    'total_outstanding' => $total,
                    'invoice_count' => $rows->count(),
                    'oldest_days_overdue' => $oldest,
                    'priority' => $this->priority(
                        $total,
                        $oldest,
                        $rows->count()
                    ),
                    'invoices' => $rows
                        ->sortByDesc('days_overdue')
                        ->values(),
                ];
            })
            ->sortByDesc('priority')
            ->values();

        return view('chase.index', [
            'clients' => $clients,
            'summary' => [
                'clients' => $clients->count(),
                'total' => (float) $clients->sum('total_outstanding'),
                'high_priority' => $clients
                    ->where('priority', '>=', 1000)
                    ->count(),
            ],
        ]);
    }

    private function priority(
        float $outstanding,
        int $oldestDaysOverdue,
        int $invoiceCount
    ): float {
        return
            ($outstanding / 10)
            + ($oldestDaysOverdue * 3)
            + ($invoiceCount * 15);
    }
}
