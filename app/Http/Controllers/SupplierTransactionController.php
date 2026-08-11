<?php

namespace App\Http\Controllers;

use App\Domains\Suppliers\Actions\AllocateSupplierTransaction;
use App\Domains\Suppliers\Services\SupplierTransactionService;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\SupplierProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierTransactionController extends Controller
{
    public function index(
        SupplierProfile $supplier,
        SupplierTransactionService $transactions
    ): View {
        return view(
            'suppliers.transactions',
            [
                'supplier' => $supplier,

                'transactions' => $transactions->forSupplier(
                    $supplier
                ),

                'clients' => Client::query()
                    ->orderBy('name')
                    ->get(),
            ]
        );
    }

    public function update(
        Request $request,
        SupplierProfile $supplier,
        BankTransaction $transaction,
        AllocateSupplierTransaction $allocate
    ): RedirectResponse {
        $validated = $request->validate([
            'purpose' => [
                'required',
                'in:client,internal,shared,cancel,unknown',
            ],

            'client_id' => [
                'nullable',
                'exists:clients,id',
            ],
        ]);

        if (
            $validated['purpose'] === 'client'
            && empty($validated['client_id'])
        ) {
            return back()->withErrors([
                'client_id' => 'Choose a client for client costs.',
            ]);
        }

        $allocate->execute(
            $transaction,
            $validated['purpose'],
            $validated['client_id'] ?? null,
            $request->user()
        );

        return redirect()
            ->route(
                'suppliers.transactions.index',
                $supplier
            )
            ->with(
                'success',
                'Cost reviewed.'
            );
    }
}
