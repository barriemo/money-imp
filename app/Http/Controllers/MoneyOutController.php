<?php

namespace App\Http\Controllers;

use App\Domains\MoneyOut\Services\MoneyOutCategorisationService;
use App\Domains\MoneyOut\Services\SupplierLearningService;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\ImportRow;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MoneyOutController extends Controller
{
    public function index(): View
    {
        $rows = ImportRow::query()
            ->with([
                'batch',
                'supplier',
                'category',
                'client',
            ])
            ->where('amount', '<', 0)
            ->whereIn('classification_status', [
                'unclassified',
                'needs_review',
                'suggested',
            ])
            ->orderByDesc('transaction_date')
            ->paginate(50);

        return view('money-out.index', [
            'rows' => $rows,

            'suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(),

            'categories' => ExpenseCategory::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(),

            'clients' => Client::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),

            'summary' => [
                'needs_review' => ImportRow::query()
                    ->where('amount', '<', 0)
                    ->whereIn('classification_status', [
                        'unclassified',
                        'needs_review',
                        'suggested',
                    ])
                    ->count(),

                'reviewed' => ImportRow::query()
                    ->where('amount', '<', 0)
                    ->where(
                        'classification_status',
                        'reviewed'
                    )
                    ->count(),
            ],
        ]);
    }

    public function categorise(
        MoneyOutCategorisationService $categoriser
    ): RedirectResponse {
        ImportRow::query()
            ->where('amount', '<', 0)
            ->where('classification_status', 'unclassified')
            ->chunkById(
                100,
                function ($rows) use ($categoriser): void {
                    foreach ($rows as $row) {
                        $categoriser->categorise($row);
                    }
                }
            );

        return back()->with(
            'success',
            'Money Out categorisation complete.'
        );
    }

    public function review(
        Request $request,
        ImportRow $row,
        SupplierLearningService $learning
    ): RedirectResponse {
        $validated = $request->validate([
            'supplier_id' => [
                'required',
                'uuid',
                'exists:suppliers,id',
            ],

            'expense_category_id' => [
                'required',
                'uuid',
                'exists:expense_categories,id',
            ],

            'client_id' => [
                'nullable',
                'uuid',
                'exists:clients,id',
            ],

            'remember' => [
                'nullable',
                'boolean',
            ],
        ]);

        $learning->confirm(
            $row,
            Supplier::findOrFail(
                $validated['supplier_id']
            ),
            ExpenseCategory::findOrFail(
                $validated['expense_category_id']
            ),
            isset($validated['client_id'])
                ? Client::findOrFail(
                    $validated['client_id']
                )
                : null,
            (bool) ($validated['remember'] ?? false),
            $request->user()->id
        );

        return back()->with(
            'success',
            'Expense reviewed and learned.'
        );
    }
}
