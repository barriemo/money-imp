<?php

namespace App\Http\Controllers;

use App\Domains\Suppliers\Assets\Actions\ReviewSupplierAsset;
use App\Models\Client;
use App\Models\SupplierAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierAssetController extends Controller
{
    public function index(): View
    {
        $assets = SupplierAsset::query()
            ->with([
                'supplier',
                'client',
            ])
            ->orderByDesc('active')
            ->orderByDesc('observed_cost')
            ->orderBy('name')
            ->get();

        return view(
            'suppliers.assets.index',
            [
                'assets' => $assets,

                'clients' => Client::query()
                    ->orderBy('name')
                    ->get(),

                'monthlyCost' => $assets
                    ->where('active', true)
                    ->sum(
                        fn (SupplierAsset $asset) => (float) $asset
                            ->observed_cost
                    ),

                'billableCost' => $assets
                    ->where('active', true)
                    ->where('billable', true)
                    ->sum(
                        fn (SupplierAsset $asset) => (float) $asset
                            ->observed_cost
                    ),

                'expectedRecovery' => $assets
                    ->where('active', true)
                    ->where('billable', true)
                    ->sum(
                        fn (SupplierAsset $asset) => (float) (
                            $asset
                                ->expected_charge
                            ?? 0
                        )
                    ),

                'unassignedCount' => $assets
                    ->whereNull('purpose')
                    ->count(),
            ]
        );
    }

    public function update(
        Request $request,
        SupplierAsset $asset,
        ReviewSupplierAsset $review
    ): RedirectResponse {
        $validated = $request->validate([
            'purpose' => [
                'required',
                'in:client,internal,shared,dead,cancel,unknown',
            ],

            'client_id' => [
                'nullable',
                'exists:clients,id',
            ],

            'billable' => [
                'nullable',
                'boolean',
            ],

            'expected_charge' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        if (
            $validated['purpose'] === 'client'
            && empty($validated['client_id'])
        ) {
            return back()->withErrors([
                'client_id' => 'Choose the client that owns this asset.',
            ]);
        }

        $review->execute(
            $asset,
            $validated['purpose'],
            $validated['client_id'] ?? null,
            $request->boolean('billable'),
            isset($validated['expected_charge'])
                ? (float) $validated[
                    'expected_charge'
                ]
                : null,
            $validated['notes'] ?? null
        );

        return back()->with(
            'success',
            'Infrastructure asset reviewed.'
        );
    }
}
