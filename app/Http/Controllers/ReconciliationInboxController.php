<?php

namespace App\Http\Controllers;

use App\Domains\Reconciliation\Resolution\ReconciliationSuggestionResolutionService;
use App\Domains\Reconciliation\Review\ReconciliationReviewPriorityService;
use App\Domains\Reconciliation\Services\PayerIdentityService;
use App\Domains\Reconciliation\Services\PaymentAllocationApprovalService;
use App\Domains\Reconciliation\Services\ReconciliationEvidencePublisher;
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
    public function index(
        Request $request,
        ReconciliationReviewPriorityService $reviewPriority,
        ReconciliationSuggestionResolutionService $resolution
    ): View {
        $tab =
            (string) $request
                ->query(
                    'tab',
                    'unknown'
                );

        $transactions = null;
        $reviewItems = null;
        $historicalItems = null;
        $reviewBandCounts = [];
        $resolutionCandidates = [];
        $historicalClassifications = [];

        if ($tab === 'ready') {
            $ready =
                $reviewPriority->ready();

            $reviewBandCounts =
                $reviewPriority->bandCounts(
                    $ready
                );

            $reviewItems =
                $reviewPriority->paginate(
                    items: $ready,

                    page: (int) $request
                        ->query(
                            'page',
                            1
                        ),

                    perPage: 50,

                    path: route(
                        'reconciliation.index'
                    ),

                    query: [
                        'tab' => 'ready',
                    ]
                );

            foreach ($reviewItems as $review) {
                if (
                    ! in_array(
                        $review->band,
                        [
                            'needs_care',
                            'stale',
                        ],
                        true
                    )
                ) {
                    continue;
                }

                $allocation =
                    $review->allocation;

                $resolutionCandidates[
                    $allocation->id
                ] =
                    $resolution
                        ->candidates(
                            $allocation
                        )
                        ->all();

                if (
                    $review->band
                    === 'stale'
                ) {
                    $historicalClassifications[
                        $allocation->id
                    ] =
                        $resolution
                            ->historicalClassification(
                                $allocation
                            );
                }
            }
        } elseif ($tab === 'historical') {
            $historicalItems =
                PaymentAllocation::query()
                    ->where(
                        'status',
                        PaymentAllocation::STATUS_HISTORICAL_CORROBORATION
                    )
                    ->with([
                        'transaction.client',
                        'transaction.bankAccount',
                        'invoice.client',
                    ])
                    ->orderByDesc(
                        'updated_at'
                    )
                    ->paginate(
                        50
                    )
                    ->withQueryString();
        } else {
            $query =
                BankTransaction::query()
                    ->with([
                        'client',
                        'bankAccount',
                        'paymentAllocations.invoice',
                        'client.invoices' => fn ($query) => $query
                            ->where(
                                'outstanding_amount',
                                '>',
                                0
                            )
                            ->orderBy(
                                'due_date'
                            ),
                    ])
                    ->where(
                        'amount',
                        '>',
                        0
                    )
                    ->orderByDesc(
                        'transaction_date'
                    )
                    ->orderByDesc(
                        'created_at'
                    );

            match ($tab) {
                'known' => $query
                    ->where(
                        'match_status',
                        'suggested'
                    )
                    ->whereNotNull(
                        'client_id'
                    )
                    ->whereDoesntHave(
                        'paymentAllocations',
                        fn ($query) => $query
                            ->whereIn(
                                'status',
                                [
                                    'suggested',
                                    PaymentAllocation::STATUS_HISTORICAL_CORROBORATION,
                                ]
                            )
                    ),

                'ignored' => $query
                    ->where(
                        'match_status',
                        'ignored'
                    ),

                default => $query
                    ->where(
                        'match_status',
                        'unmatched'
                    ),
            };

            $transactions =
                $query
                    ->paginate(
                        50
                    )
                    ->withQueryString();
        }

        return view(
            'reconciliation.index',
            [
                'tab' => $tab,

                'transactions' => $transactions,

                'reviewItems' => $reviewItems,

                'historicalItems' => $historicalItems,

                'reviewBandCounts' => $reviewBandCounts,

                'resolutionCandidates' => $resolutionCandidates,

                'historicalClassifications' => $historicalClassifications,

                'clients' => Client::query()
                    ->where(
                        'status',
                        'active'
                    )
                    ->orderBy(
                        'name'
                    )
                    ->get([
                        'id',
                        'name',
                    ]),

                'counts' => [
                    'ready' => PaymentAllocation::query()
                        ->where(
                            'status',
                            'suggested'
                        )
                        ->count(),

                    'historical' => PaymentAllocation::query()
                        ->where(
                            'status',
                            PaymentAllocation::STATUS_HISTORICAL_CORROBORATION
                        )
                        ->count(),

                    'known' => BankTransaction::query()
                        ->where(
                            'amount',
                            '>',
                            0
                        )
                        ->where(
                            'match_status',
                            'suggested'
                        )
                        ->whereNotNull(
                            'client_id'
                        )
                        ->whereDoesntHave(
                            'paymentAllocations',
                            fn ($query) => $query
                                ->whereIn(
                                    'status',
                                    [
                                        'suggested',
                                        PaymentAllocation::STATUS_HISTORICAL_CORROBORATION,
                                    ]
                                )
                        )
                        ->count(),

                    'unknown' => BankTransaction::query()
                        ->where(
                            'amount',
                            '>',
                            0
                        )
                        ->where(
                            'match_status',
                            'unmatched'
                        )
                        ->count(),

                    'ignored' => BankTransaction::query()
                        ->where(
                            'amount',
                            '>',
                            0
                        )
                        ->where(
                            'match_status',
                            'ignored'
                        )
                        ->count(),
                ],
            ]
        );
    }

    public function approveSuggestion(
        Request $request,
        PaymentAllocation $allocation,
        PaymentAllocationApprovalService $approval,
        ReconciliationReviewPriorityService $reviewPriority
    ): RedirectResponse {
        $priority =
            $reviewPriority->forAllocation(
                $allocation
            );

        if (
            ! $priority->actionable
            || $priority->band === 'needs_care'
        ) {
            return back()->with(
                'error',
                $priority->band === 'needs_care'
                    ? 'This suggestion has competing reconciliation evidence. Resolve it against an explicit invoice instead of using generic approval.'
                    : 'This suggestion cannot currently be approved. Review the warning and reject or correct the evidence first.'
            );
        }

        $approved =
            $approval->approve(
                allocation: $allocation,

                userId: (string) $request
                    ->user()
                    ->id
            );

        return back()->with(
            'success',
            'Approved £'
                .number_format(
                    (float) $approved->amount,
                    2
                )
                .' against invoice '
                .(
                    $approved
                        ->invoice
                        ?->invoice_number
                    ?? 'unknown'
                )
                .'.'
        );
    }

    public function rejectSuggestion(
        Request $request,
        PaymentAllocation $allocation,
        PaymentAllocationApprovalService $approval
    ): RedirectResponse {
        $approval->reject(
            allocation: $allocation,

            userId: (string) $request
                ->user()
                ->id,

            reason: 'Rejected during prioritised reconciliation review.'
        );

        return back()->with(
            'success',
            'Suggestion rejected. The transaction remains available for further reconciliation.'
        );
    }

    public function resolveHistoricalSuggestion(
        Request $request,
        PaymentAllocation $allocation,
        ReconciliationSuggestionResolutionService $resolution
    ): RedirectResponse {
        $validated =
            $request->validate([
                'invoice_id' => [
                    'required',
                    'uuid',
                    'exists:accounting_invoices,id',
                ],
            ]);

        $invoice =
            AccountingInvoice::findOrFail(
                $validated[
                    'invoice_id'
                ]
            );

        $resolved =
            $resolution->resolveHistorical(
                allocation: $allocation,

                invoice: $invoice,

                userId: (string) $request
                    ->user()
                    ->id
            );

        return back()->with(
            'success',
            'Recorded £'
                .number_format(
                    (float) $resolved->amount,
                    2
                )
                .' as historical corroboration for invoice '
                .(
                    $resolved
                        ->invoice
                        ?->invoice_number
                    ?? 'unknown'
                )
                .'. This preserves the bank-to-invoice evidence but does not create an approved/imported invoice allocation.'
        );
    }

    public function resolveApprovedSuggestion(
        Request $request,
        PaymentAllocation $allocation,
        ReconciliationSuggestionResolutionService $resolution
    ): RedirectResponse {
        $validated =
            $request->validate([
                'invoice_id' => [
                    'required',
                    'uuid',
                    'exists:accounting_invoices,id',
                ],
            ]);

        $invoice =
            AccountingInvoice::findOrFail(
                $validated[
                    'invoice_id'
                ]
            );

        $resolved =
            $resolution->resolveApproved(
                allocation: $allocation,

                invoice: $invoice,

                userId: (string) $request
                    ->user()
                    ->id
            );

        return back()->with(
            'success',
            'Approved £'
                .number_format(
                    (float) $resolved->amount,
                    2
                )
                .' against invoice '
                .(
                    $resolved
                        ->invoice
                        ?->invoice_number
                    ?? 'unknown'
                )
                .' after recurring-payment review.'
        );
    }

    public function assignClient(
        Request $request,
        BankTransaction $transaction,
        PayerIdentityService $identities,
        ReconciliationEvidencePublisher $evidence
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

        $evidence->publish(
            type: 'client_payment_attribution_changed',

            clientId: $clientId,

            metadata: [
                'transaction_id' => $transaction->id,

                'remember_identity' => $request->boolean(
                    'remember_identity'
                ),

                'bulk_assigned' => $bulkAssigned,
            ]
        );

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
        PaymentAllocationApprovalService $approval
    ): RedirectResponse {
        $validated = $request->validate([
            'invoice_id' => ['required', 'uuid', 'exists:accounting_invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $invoice = AccountingInvoice::findOrFail(
            $validated['invoice_id']
        );

        $allocation = $approval->approveManual(
            transaction: $transaction,
            invoice: $invoice,
            requestedAmount: (float) $validated['amount'],
            userId: $request->user()->id,
        );

        return back()->with(
            'success',
            '£'.number_format((float) $allocation->amount, 2)
                .' allocated to invoice '
                .$invoice->invoice_number.'.'
        );
    }

    public function ignore(
        Request $request,
        BankTransaction $transaction,
        ReconciliationEvidencePublisher $evidence
    ): RedirectResponse {
        $affectedClientId =
            $transaction->client_id;

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

        $evidence->publish(
            type: 'bank_transaction_classification_changed',

            clientId: $affectedClientId,

            metadata: [
                'transaction_id' => $transaction->id,

                'classification' => 'non_client_income',
            ]
        );

        return back()->with(
            'success',
            'Transaction classified as non-client income.'
        );
    }
}
