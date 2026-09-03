<?php

namespace App\Domains\Reconciliation\Review;

use App\Domains\Accounting\Services\InvoiceBalanceService;
use App\Models\PaymentAllocation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ReconciliationReviewPriorityService
{
    public function __construct(
        private readonly InvoiceBalanceService $balances,
    ) {}

    /**
     * This is review ordering only.
     *
     * The score is not payment truth, investigation confidence,
     * or an auto-approval threshold.
     *
     * @return Collection<int, ReconciliationReviewPriority>
     */
    public function ready(): Collection
    {
        return PaymentAllocation::query()
            ->where(
                'status',
                'suggested'
            )
            ->with([
                'transaction.client',
                'transaction.bankAccount',
                'transaction.paymentAllocations',
                'invoice.client',
                'invoice.paymentAllocations',
            ])
            ->get()
            ->map(
                fn (PaymentAllocation $allocation) => $this->forAllocation(
                    $allocation
                )
            )
            ->sort(
                fn (
                    ReconciliationReviewPriority $a,
                    ReconciliationReviewPriority $b
                ) => $this->compare(
                    $a,
                    $b
                )
            )
            ->values();
    }

    public function forAllocation(
        PaymentAllocation $allocation
    ): ReconciliationReviewPriority {
        $allocation->loadMissing([
            'transaction.client',
            'transaction.bankAccount',
            'transaction.paymentAllocations',
            'invoice.client',
            'invoice.paymentAllocations',
        ]);

        $transaction =
            $allocation->transaction;

        $invoice =
            $allocation->invoice;

        $reasons = [];
        $warnings = [];

        if (
            ! $transaction
            || ! $invoice
        ) {
            return new ReconciliationReviewPriority(
                allocation: $allocation,

                score: 0,

                band: 'needs_care',

                bandLabel: 'Needs care',

                actionable: false,

                sourceOutstanding: 0,

                invoiceBalance: 0,

                effectiveApprovalAmount: 0,

                reasons: [],

                warnings: [
                    'The suggested allocation is missing its transaction or invoice evidence.',
                ],

                humanAttributed: false,

                automatedCandidate: false,

                invoiceSuggestionCount: 0,

                transactionSuggestionCount: 0,
            );
        }

        $sourceOutstanding =
            round(
                max(
                    0,
                    (float) $invoice
                        ->outstanding_amount
                ),
                2
            );

        $invoiceBalance =
            round(
                (float) $this
                    ->balances
                    ->outstanding(
                        $invoice
                    ),
                2
            );

        $transactionApprovedAmount =
            round(
                (float) $transaction
                    ->paymentAllocations
                    ->whereIn(
                        'status',
                        [
                            'approved',
                            'imported',
                        ]
                    )
                    ->sum(
                        'amount'
                    ),
                2
            );

        $paymentAvailable =
            round(
                max(
                    0,
                    (float) $transaction->amount
                    - $transactionApprovedAmount
                ),
                2
            );

        $effectiveApprovalAmount =
            round(
                min(
                    (float) $allocation->amount,
                    $paymentAvailable,
                    $invoiceBalance
                ),
                2
            );

        $invoiceSuggestionCount =
            $invoice
                ->paymentAllocations
                ->where(
                    'status',
                    'suggested'
                )
                ->count();

        $transactionSuggestionCount =
            $transaction
                ->paymentAllocations
                ->where(
                    'status',
                    'suggested'
                )
                ->count();

        $humanAttributed =
            $transaction->matched_by
            !== null;

        $automatedCandidate =
            (
                $transaction->metadata[
                    'reconciliation_provenance'
                ]
                ?? null
            ) === 'automated_candidate';

        $sameClient =
            $transaction->client_id
            !== null
            && $invoice->client_id
            !== null
            && $transaction->client_id
                === $invoice->client_id;

        /*
         * Evidence structure carries the review score.
         *
         * Existing raw confidence numbers are deliberately not
         * scored because production discovery showed all 29
         * suggestions currently report transaction confidence 100
         * despite materially different evidence quality.
         */
        $score =
            match (
                $allocation->match_method
            ) {
                'client_and_invoice_reference' => 70,

                'client_and_exact_amount' => 55,

                'canonical_client_exact_amount' => 50,

                'canonical_chronological_exact_amount' => 42,

                default => 25,
            };

        $reasons[] =
            match (
                $allocation->match_method
            ) {
                'client_and_invoice_reference' => 'Invoice reference matched the bank transaction.',

                'client_and_exact_amount' => 'Client matched and exactly one invoice amount matched.',

                'canonical_client_exact_amount' => 'Canonical client evidence and invoice amount matched.',

                'canonical_chronological_exact_amount' => 'Canonical client evidence and chronological invoice amount matched.',

                default => 'A reconciliation engine proposed this invoice.',
            };

        if ($humanAttributed) {
            $score += 10;

            $reasons[] =
                'The client attribution was made by a human.';
        } elseif ($automatedCandidate) {
            $reasons[] =
                'The client attribution was proposed automatically and still requires human review.';
        } else {
            $reasons[] =
                'Client attribution provenance predates explicit automated/human provenance tracking.';
        }

        if ($sameClient) {
            $score += 5;

            $reasons[] =
                'The transaction and invoice currently belong to the same client.';
        } else {
            $score -= 40;

            $warnings[] =
                'The transaction and invoice client do not agree.';
        }

        if (
            $invoiceBalance > 0
            && abs(
                $effectiveApprovalAmount
                - $invoiceBalance
            ) < 0.01
        ) {
            $score += 5;

            $reasons[] =
                'The suggested amount would cover the current allocatable invoice balance.';
        }

        /*
         * Conflict penalties.
         *
         * Multiple payments targeting the same invoice are not
         * automatically wrong, but they require a human to decide
         * which receipt belongs against that invoice.
         */
        if (
            $invoiceSuggestionCount > 1
        ) {
            $score -= 30;

            $warnings[] =
                sprintf(
                    '%d suggested payments currently target this invoice.',
                    $invoiceSuggestionCount
                );
        }

        if (
            $transactionSuggestionCount > 1
        ) {
            $score -= 25;

            $warnings[] =
                sprintf(
                    'This bank transaction currently targets %d suggested invoices.',
                    $transactionSuggestionCount
                );
        }

        if (
            $invoiceBalance <= 0.009
        ) {
            $score = 0;

            $warnings[] =
                'The invoice has no remaining allocatable balance.';
        }

        if (
            $paymentAvailable <= 0.009
        ) {
            $score = 0;

            $warnings[] =
                'The bank transaction has no remaining amount available to allocate.';
        }

        if (
            (float) $transaction->amount
            <= 0
        ) {
            $score = 0;

            $warnings[] =
                'The transaction is not an incoming payment.';
        }

        $score =
            max(
                0,
                min(
                    100,
                    $score
                )
            );

        $actionable =
            $allocation->status
                === 'suggested'
            && $sameClient
            && $invoiceBalance
                > 0.009
            && $paymentAvailable
                > 0.009
            && $effectiveApprovalAmount
                > 0.009
            && (float) $transaction->amount
                > 0;

        $stale =
            $invoiceBalance
                <= 0.009
            || $paymentAvailable
                <= 0.009;

        [
            $band,
            $bandLabel,
        ] =
            match (true) {
                $stale => [
                    'stale',
                    'Stale suggestion',
                ],

                ! $actionable
                    || $warnings !== [] => [
                        'needs_care',
                        'Needs care',
                    ],

                $score >= 90 => [
                    'review_first',
                    'Review first',
                ],

                $score >= 75 => [
                    'strong_review',
                    'Strong review',
                ],

                $score >= 50 => [
                    'normal_review',
                    'Normal review',
                ],

                default => [
                    'needs_care',
                    'Needs care',
                ],
            };

        return new ReconciliationReviewPriority(
            allocation: $allocation,

            score: $score,

            band: $band,

            bandLabel: $bandLabel,

            actionable: $actionable,

            sourceOutstanding: $sourceOutstanding,

            invoiceBalance: $invoiceBalance,

            effectiveApprovalAmount: $effectiveApprovalAmount,

            reasons: array_values(
                array_unique(
                    $reasons
                )
            ),

            warnings: array_values(
                array_unique(
                    $warnings
                )
            ),

            humanAttributed: $humanAttributed,

            automatedCandidate: $automatedCandidate,

            invoiceSuggestionCount: $invoiceSuggestionCount,

            transactionSuggestionCount: $transactionSuggestionCount,
        );
    }

    public function paginate(
        Collection $items,
        int $page,
        int $perPage,
        string $path,
        array $query = []
    ): LengthAwarePaginator {
        $page =
            max(
                1,
                $page
            );

        $perPage =
            max(
                1,
                min(
                    100,
                    $perPage
                )
            );

        return new LengthAwarePaginator(
            items: $items
                ->forPage(
                    $page,
                    $perPage
                )
                ->values(),

            total: $items->count(),

            perPage: $perPage,

            currentPage: $page,

            options: [
                'path' => $path,

                'query' => $query,
            ]
        );
    }

    public function bandCounts(
        Collection $items
    ): array {
        return [
            'review_first' => $items
                ->where(
                    'band',
                    'review_first'
                )
                ->count(),

            'strong_review' => $items
                ->where(
                    'band',
                    'strong_review'
                )
                ->count(),

            'normal_review' => $items
                ->where(
                    'band',
                    'normal_review'
                )
                ->count(),

            'needs_care' => $items
                ->where(
                    'band',
                    'needs_care'
                )
                ->count(),

            'stale' => $items
                ->where(
                    'band',
                    'stale'
                )
                ->count(),
        ];
    }

    private function compare(
        ReconciliationReviewPriority $a,
        ReconciliationReviewPriority $b
    ): int {
        $weight = [
            'review_first' => 0,
            'strong_review' => 1,
            'normal_review' => 2,
            'needs_care' => 3,
            'stale' => 4,
        ];

        $bandComparison =
            (
                $weight[
                    $a->band
                ] ?? 99
            )
            <=>
            (
                $weight[
                    $b->band
                ] ?? 99
            );

        if (
            $bandComparison
            !== 0
        ) {
            return $bandComparison;
        }

        if (
            $a->score
            !== $b->score
        ) {
            return $b->score
                <=>
                $a->score;
        }

        if (
            $a->effectiveApprovalAmount
            !== $b->effectiveApprovalAmount
        ) {
            return $b->effectiveApprovalAmount
                <=>
                $a->effectiveApprovalAmount;
        }

        $aDate =
            $a
                ->allocation
                ->transaction
                ?->transaction_date
                ?->timestamp
            ?? 0;

        $bDate =
            $b
                ->allocation
                ->transaction
                ?->transaction_date
                ?->timestamp
            ?? 0;

        return $bDate
            <=>
            $aDate;
    }
}
