<?php

namespace App\Http\Controllers;

use App\Domains\FinancialTruth\Services\FinancialTruthService;
use App\Models\AccountBalanceSnapshot;
use App\Models\BankAccount;
use App\Models\Liability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialTruthController extends Controller
{
    public function index(
        FinancialTruthService $truth
    ): View {
        return view(
            'financial-truth.index',
            [
                'truth' => $truth->build(),
                'bankAccounts' => BankAccount::query()
                    ->orderBy('name')
                    ->get(),
            ]
        );
    }

    public function storeBalance(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'bank_account_id' => [
                'required',
                'exists:bank_accounts,id',
            ],
            'balance' => [
                'required',
                'numeric',
            ],
            'balance_at' => [
                'required',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $account = BankAccount::findOrFail(
            $validated['bank_account_id']
        );

        $balance = (float) $validated['balance'];

        /*
         * Credit card balances entered as debt are stored
         * as negative values in the financial truth ledger.
         */
        if (
            $account->account_type
            === 'CreditCardAccount'
        ) {
            $balance = -abs($balance);
        }

        AccountBalanceSnapshot::create([
            'bank_account_id' => $account->id,
            'balance' => $balance,
            'source' => 'manual_verified',
            'balance_at' => $validated['balance_at'],
            'verified' => true,
            'confidence' => 100,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('financial-truth.index')
            ->with(
                'success',
                $account->name
                .' balance verified.'
            );
    }

    public function storeLiability(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'type' => [
                'required',
                'in:vat,paye,corporation_tax,loan,creditor,other',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'due_date' => [
                'nullable',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        Liability::updateOrCreate(
            [
                'type' => $validated['type'],
                'name' => $validated['name'],
                'status' => 'open',
            ],
            [
                'amount' => $validated['amount'],
                'due_date' => $validated['due_date'] ?? null,
                'source' => 'manual_verified',
                'verified' => true,
                'confidence' => 100,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return redirect()
            ->route('financial-truth.index')
            ->with(
                'success',
                $validated['name']
                .' liability verified.'
            );
    }
}
