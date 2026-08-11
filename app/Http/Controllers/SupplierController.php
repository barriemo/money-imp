<?php

namespace App\Http\Controllers;

use App\Domains\Suppliers\Services\SupplierAnalysisService;
use App\Domains\Suppliers\Services\SupplierRecoveryService;
use App\Models\SupplierProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(
        SupplierAnalysisService $analysis,
        SupplierRecoveryService $recovery
    ): View {
        $suppliers = $analysis
            ->analyseAll();

        return view(
            'suppliers.index',
            [
                'suppliers' => $suppliers,

                'totalMonthly' => $suppliers->sum(
                    'averageMonthlySpend'
                ),

                'totalAnnual' => $suppliers->sum(
                    'annualisedSpend'
                ),

                'totalUnallocated' => $suppliers->sum(
                    'unallocatedSpend'
                ),

                'recoverableLeakage' => $suppliers->sum(
                    fn ($item) => $recovery->leakage(
                        $item->supplier
                    )
                ),
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'supplier_name' => [
                'required',
                'string',
                'max:255',
            ],

            'category' => [
                'nullable',
                'string',
                'max:100',
            ],

            'recoverable' => [
                'nullable',
                'boolean',
            ],
        ]);

        SupplierProfile::firstOrCreate(
            [
                'supplier_key' => Str::of(
                    $validated['supplier_name']
                )
                    ->lower()
                    ->replaceMatches(
                        '/[^a-z0-9]+/',
                        ' '
                    )
                    ->squish()
                    ->value(),
            ],
            [
                'supplier_name' => $validated['supplier_name'],

                'category' => $validated['category']
                    ?? null,

                'recoverable' => $request->boolean(
                    'recoverable'
                ),

                'active' => true,
            ]
        );

        return redirect()
            ->route('suppliers.index');
    }
}
