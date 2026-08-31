<?php

namespace App\Http\Controllers;

use App\Domains\Suppliers\Payments\Services\SupplierPaymentAllocationApprovalService;
use App\Domains\Suppliers\Payments\Services\SupplierPaymentCandidateService;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierPaymentReconciliationController extends Controller
{
    public function index(): View
    {
        $allocations = SupplierPaymentAllocation::query()
            ->with([
                'transaction.bankAccount',
                'bill.supplier',
            ])
            ->where('status', 'suggested')
            ->latest()
            ->paginate(50);

        $summary = [
            'suggested' => SupplierPaymentAllocation::query()
                ->where('status', 'suggested')
                ->count(),

            'approved' => SupplierPaymentAllocation::query()
                ->where('status', 'approved')
                ->count(),

            'rejected' => SupplierPaymentAllocation::query()
                ->where('status', 'rejected')
                ->count(),

            'suggested_value' => SupplierPaymentAllocation::query()
                ->where('status', 'suggested')
                ->sum('amount'),
        ];

        return view('supplier-payments.index', [
            'allocations' => $allocations,
            'summary' => $summary,
        ]);
    }

    public function generate(
        SupplierPaymentCandidateService $candidates
    ): RedirectResponse {
        $stats = $candidates->generate();

        return back()->with(
            'success',
            $stats['bill_matches']
            .' supplier payment suggestion(s) generated. '
            .$stats['ambiguous']
            .' ambiguous match(es) skipped. '
            .$stats['unmatched']
            .' unmatched payment(s).'
        );
    }

    public function approve(
        SupplierPaymentAllocation $allocation,
        SupplierPaymentAllocationApprovalService $approval
    ): RedirectResponse {
        $approval->approve(
            $allocation,
            request()->user()->id
        );

        return back()->with(
            'success',
            'Supplier payment allocation approved.'
        );
    }

    public function reject(
        SupplierPaymentAllocation $allocation,
        SupplierPaymentAllocationApprovalService $approval
    ): RedirectResponse {
        $approval->reject(
            $allocation,
            request()->user()->id,
            request('reason')
        );

        return back()->with(
            'success',
            'Supplier payment allocation rejected.'
        );
    }
}
