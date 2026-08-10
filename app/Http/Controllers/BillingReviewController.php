<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Services\BulkInvoiceSendService;
use App\Models\AccountingInvoice;
use App\Models\BillingReview;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingReviewController extends Controller
{
    public function index(): View
    {
        $start = CarbonImmutable::create(2026, 7, 1)->startOfMonth();
        $end = $start->endOfMonth();

        $invoices = AccountingInvoice::query()
            ->with([
                'client',
                'billingReview',
                'items',
            ])
            ->where('status', 'draft')
            ->whereBetween('invoice_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->orderBy('invoice_date')
            ->get();

        return view('billing.review', [
            'invoices' => $invoices,
            'summary' => [
                'drafts' => $invoices->count(),
                'approved' => $invoices
                    ->filter(
                        fn ($invoice) => $invoice->billingReview?->status === 'approved'
                    )
                    ->count(),
                'pending' => $invoices
                    ->filter(
                        fn ($invoice) => $invoice->billingReview?->status !== 'approved'
                    )
                    ->count(),
                'value' => (float) $invoices->sum('gross_amount'),
            ],
        ]);
    }

    public function approve(
        Request $request,
        AccountingInvoice $invoice
    ): RedirectResponse {
        abort_unless(
            $invoice->status === 'draft',
            422,
            'Only draft invoices can be approved.'
        );

        BillingReview::updateOrCreate(
            [
                'accounting_invoice_id' => $invoice->id,
            ],
            [
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]
        );

        return back()->with(
            'success',
            'Invoice '.$invoice->invoice_number.' approved.'
        );
    }

    public function approveBulk(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'invoices' => ['required', 'array', 'min:1'],
            'invoices.*' => [
                'required',
                'uuid',
                'exists:accounting_invoices,id',
            ],
        ]);

        $invoices = AccountingInvoice::query()
            ->whereIn('id', $validated['invoices'])
            ->where('status', 'draft')
            ->get();

        foreach ($invoices as $invoice) {
            BillingReview::updateOrCreate(
                [
                    'accounting_invoice_id' => $invoice->id,
                ],
                [
                    'status' => 'approved',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]
            );
        }

        return back()->with(
            'success',
            $invoices->count().' draft invoice(s) approved.'
        );
    }

    public function sendApproved(
        Request $request,
        BulkInvoiceSendService $sender
    ): RedirectResponse {
        $validated = $request->validate([
            'invoices' => ['required', 'array', 'min:1'],
            'invoices.*' => [
                'required',
                'uuid',
                'exists:accounting_invoices,id',
            ],
        ]);

        $approvedIds = AccountingInvoice::query()
            ->whereIn('id', $validated['invoices'])
            ->where('status', 'draft')
            ->whereHas(
                'billingReview',
                fn ($query) => $query->where('status', 'approved')
            )
            ->pluck('id')
            ->all();

        if ($approvedIds === []) {
            return back()->withErrors([
                'billing' => 'No selected invoices are approved and eligible to send.',
            ]);
        }

        $result = $sender->send($approvedIds);

        session()->flash(
            'send_result',
            $result
        );

        return back()->with(
            'success',
            count($result['sent'])
                .' invoice(s) emailed through FreeAgent. '
                .count($result['failed'])
                .' failed.'
        );
    }
}
