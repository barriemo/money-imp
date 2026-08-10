<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Services\BulkDraftInvoiceService;
use App\Domains\Billing\Services\FreeAgentDraftInvoiceService;
use App\Domains\Billing\Services\MonthlyBillingAuditService;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BillingQueueController extends Controller
{
    public function index(
        Request $request,
        MonthlyBillingAuditService $audit
    ): View {
        $month = CarbonImmutable::create(2026, 7, 1);

        $result = $audit->audit($month);

        return view('billing.index', [
            'month' => $result['month'],
            'rows' => $result['rows'],
            'summary' => $result['summary'],
        ]);
    }

    public function createBulkDrafts(
        Request $request,
        MonthlyBillingAuditService $audit,
        BulkDraftInvoiceService $bulk
    ): RedirectResponse {
        $validated = $request->validate([
            'clients' => ['required', 'array', 'min:1'],
            'clients.*' => ['required', 'uuid', 'exists:clients,id'],
        ]);

        $month = CarbonImmutable::create(2026, 7, 1);

        $billing = $audit->audit($month);

        $safeClientIds = $billing['rows']
            ->where('status', 'missing')
            ->pluck('client.id')
            ->intersect($validated['clients'])
            ->values()
            ->all();

        if ($safeClientIds === []) {
            return back()->withErrors([
                'billing' => 'No selected clients are currently safe to draft.',
            ]);
        }

        $result = $bulk->create(
            $safeClientIds,
            $month
        );

        session()->flash(
            'bulk_billing_result',
            $result
        );

        return back()->with(
            'success',
            count($result['created'])
                .' FreeAgent draft(s) created. '
                .count($result['failed'])
                .' failed.'
        );
    }

    public function createDraft(
        Client $client,
        FreeAgentDraftInvoiceService $drafts
    ): RedirectResponse {
        try {
            $invoice = $drafts->createMonthlyDraft(
                $client,
                CarbonImmutable::create(2026, 7, 1)
            );

            return back()->with(
                'success',
                'FreeAgent draft created for '
                .$client->name
                .' — invoice '
                .($invoice['reference'] ?? 'created')
                .'. Sync invoices to refresh Money Imp.'
            );
        } catch (Throwable $exception) {
            return back()->withErrors([
                'billing' => $exception->getMessage(),
            ]);
        }
    }
}
