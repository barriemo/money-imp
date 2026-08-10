<?php

namespace App\Http\Controllers;

use App\Domains\Accounting\Services\InvoiceBalanceService;
use App\Domains\Reconciliation\Services\PayerIdentityService;
use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use App\Models\PaymentIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReconciliationInboxController extends Controller
{
    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'unknown');

        $query = BankTransaction::query()
            ->with([
                'client',
                'bankAccount',
                'paymentAllocations.invoice',
                'client.invoices' => fn ($query) => $query
                    ->where('outstanding_amount', '>', 0)
                    ->orderBy('due_date'),
            ])
            ->where('amount', '>', 0)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at');

        match ($tab) {
            'known' => $query
                ->where('match_status', 'suggested')
                ->whereNotNull('client_id')
                ->whereDoesntHave(
                    'paymentAllocations',
                    fn ($query) => $query->where('status', 'suggested')
                ),

            'ready' => $query
                ->whereHas(
                    'paymentAllocations',
                    fn ($query) => $query->where('status', 'suggested')
                ),

            'ignored' => $query
                ->where('match_status', 'ignored'),

            default => $query
                ->where('match_status', 'unmatched'),
        };

        return view('reconciliation.index', [
            'tab' => $tab,
            'transactions' => $query->paginate(50)->withQueryString(),

            'clients' => Client::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),

            'counts' => [
                'ready' => BankTransaction::query()
                    ->where('amount', '>', 0)
                    ->whereHas(
                        'paymentAllocations',
                        fn ($query) => $query->where('status', 'suggested')
                    )
                    ->count(),

                'known' => BankTransaction::query()
                    ->where('amount', '>', 0)
                    ->where('match_status', 'suggested')
                    ->whereNotNull('client_id')
                    ->whereDoesntHave(
                        'paymentAllocations',
                        fn ($query) => $query->where('status', 'suggested')
                    )
                    ->count(),

                'unknown' => BankTransaction::query()
                    ->where('amount', '>', 0)
                    ->where('match_status', 'unmatched')
                    ->count(),

                'ignored' => BankTransaction::query()
                    ->where('amount', '>', 0)
                    ->where('match_status', 'ignored')
                    ->count(),
            ],
        ]);
    }

    public function assignClient(
        Request $request,
        BankTransaction $transaction,
        PayerIdentityService $identities
    ): RedirectResponse {
        $validated = $request->validate([
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'remember_identity' => ['nullable', 'boolean'],
        ]);

        $clientId = $validated['client_id'];

        $transaction->update([
            'client_id' => $clientId,
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'match_confidence' => 100,
            'matched_by' => $request->user()->id,
            'matched_at' => now(),
        ]);

        $bulkAssigned = 0;

        if ($request->boolean('remember_identity')) {
            $identity = $identities->forTransaction($transaction);

            if ($identity !== '') {
                PaymentIdentity::updateOrCreate(
                    [
                        'identity_type' => 'bank_description',
                        'normalized_value' => $identity,
                        'direction' => 'incoming',
                    ],
                    [
                        'client_id' => $clientId,
                        'identity_value' => trim(
                            (string) $transaction->description
                        ),
                        'confidence' => 100,
                        'successful_matches' => 1,
                        'last_matched_at' => now(),
                        'created_by' => $request->user()->id,
                    ]
                );

                BankTransaction::query()
                    ->where('id', '!=', $transaction->id)
                    ->where('bank_account_id', $transaction->bank_account_id)
                    ->where('amount', '>', 0)
                    ->where('match_status', 'unmatched')
                    ->chunkById(
                        200,
                        function ($transactions) use (
                            $identities,
                            $identity,
                            $clientId,
                            $request,
                            &$bulkAssigned
                        ): void {
                            foreach ($transactions as $candidate) {
                                if (
                                    $identities->forTransaction($candidate)
                                    !== $identity
                                ) {
                                    continue;
                                }

                                $candidate->update([
                                    'client_id' => $clientId,
                                    'transaction_type' => 'customer_payment',
                                    'match_status' => 'suggested',
                                    'match_confidence' => 100,
                                    'matched_by' => $request->user()->id,
                                    'matched_at' => now(),
                                ]);

                                $bulkAssigned++;
                            }
                        }
                    );
            }
        }

        return back()->with(
            'success',
            $bulkAssigned > 0
                ? 'Client assigned. Money Imp also identified '
                    .$bulkAssigned.' matching historical payment(s).'
                : 'Client assigned.'
        );
    }

    public function allocateInvoice(
        Request $request,
        BankTransaction $transaction,
        InvoiceBalanceService $balances
    ): RedirectResponse {
        $validated = $request->validate([
            'invoice_id' => ['required', 'uuid', 'exists:accounting_invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $invoice = AccountingInvoice::findOrFail(
            $validated['invoice_id']
        );

        abort_unless(
            $transaction->client_id !== null
            && $invoice->client_id === $transaction->client_id,
            422,
            'Invoice does not belong to this client.'
        );

        $alreadyAllocated = (float) $transaction
            ->paymentAllocations()
            ->whereIn('status', ['approved', 'imported'])
            ->sum('amount');

        $availablePayment = max(
            0,
            (float) $transaction->amount - $alreadyAllocated
        );

        $invoiceOutstanding = (float) $balances->outstanding($invoice);

        $amount = min(
            (float) $validated['amount'],
            $availablePayment,
            $invoiceOutstanding
        );

        abort_if(
            $amount <= 0,
            422,
            'There is no remaining amount available to allocate.'
        );

        PaymentAllocation::updateOrCreate(
            [
                'bank_transaction_id' => $transaction->id,
                'accounting_invoice_id' => $invoice->id,
            ],
            [
                'amount' => $amount,
                'status' => 'approved',
                'confidence' => 100,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'match_method' => 'manual_reconciliation',
                'reason' => 'Approved manually in Money Imp reconciliation inbox.',
            ]
        );

        $totalAllocated = (float) $transaction
            ->paymentAllocations()
            ->whereIn('status', ['approved', 'imported'])
            ->sum('amount');

        $transaction->update([
            'match_status' => $totalAllocated + 0.01
                >= (float) $transaction->amount
                ? 'reconciled'
                : 'partially_allocated',
        ]);

        return back()->with(
            'success',
            '£'.number_format($amount, 2)
                .' allocated to invoice '
                .$invoice->invoice_number.'.'
        );
    }

    public function ignore(
        Request $request,
        BankTransaction $transaction
    ): RedirectResponse {
        DB::transaction(function () use ($request, $transaction): void {
            $transaction
                ->paymentAllocations()
                ->where('status', 'suggested')
                ->delete();

            $transaction->update([
                'client_id' => null,
                'transaction_type' => 'non_client_income',
                'match_status' => 'ignored',
                'match_confidence' => 100,
                'matched_by' => $request->user()->id,
                'matched_at' => now(),
            ]);
        });

        return back()->with(
            'success',
            'Transaction classified as non-client income.'
        );
    }
}
