<?php

namespace App\Domains\Dashboard\Services;

use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\ImportRow;
use App\Models\WorkLog;

class MorningBriefService
{
    public function build(): array
    {
        $cash = (float) BankAccount::query()
            ->where('account_type', 'StandardBankAccount')
            ->sum('current_balance');

        $creditCards = (float) BankAccount::query()
            ->where('account_type', 'CreditCardAccount')
            ->sum('current_balance');

        $outstanding = (float) AccountingInvoice::query()
            ->whereNotIn('status', [
                'draft',
                'paid',
                'refunded',
                'written_off',
                'zero_value',
            ])
            ->sum('outstanding_amount');

        $overdue = AccountingInvoice::query()
            ->whereNotIn('status', [
                'draft',
                'paid',
                'refunded',
                'written_off',
                'zero_value',
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());

        $workToReview = WorkLog::query()
            ->where('commercial_status', 'unreviewed');

        $workReadyToInvoice = WorkLog::query()
            ->where('commercial_status', 'invoice')
            ->whereNull('accounting_invoice_id');

        $expensesToReview = ImportRow::query()
            ->where('amount', '<', 0)
            ->whereIn('classification_status', [
                'unclassified',
                'needs_review',
                'suggested',
            ]);

        $drafts = AccountingInvoice::query()
            ->where('status', 'draft');

        $draftsAwaitingApproval = AccountingInvoice::query()
            ->where('status', 'draft')
            ->whereDoesntHave(
                'billingReview',
                fn ($query) => $query->where(
                    'status',
                    'approved'
                )
            );

        return [
            'cash' => [
                'bank' => $cash,
                'credit_cards' => $creditCards,
                'net' => $cash + $creditCards,
            ],

            'receivables' => [
                'outstanding' => $outstanding,
                'overdue_count' => $overdue->count(),
                'overdue_value' => (float) $overdue
                    ->sum('outstanding_amount'),
            ],

            'work' => [
                'review_count' => $workToReview->count(),
                'review_value' => (float) $workToReview
                    ->sum('commercial_value'),

                'ready_count' => $workReadyToInvoice->count(),
                'ready_value' => (float) $workReadyToInvoice
                    ->sum('commercial_value'),
            ],

            'money_out' => [
                'review_count' => $expensesToReview->count(),
            ],

            'billing' => [
                'draft_count' => $drafts->count(),

                'draft_value' => (float) $drafts
                    ->sum('gross_amount'),

                'awaiting_approval' => $draftsAwaitingApproval->count(),
            ],

            'actions' => array_values(
                array_filter([
                    $workToReview->count() > 0
                        ? [
                            'level' => 'red',
                            'title' => 'Review logged work',
                            'detail' => $workToReview->count()
                                .' item(s) · £'
                                .number_format(
                                    (float) $workToReview
                                        ->sum('commercial_value'),
                                    2
                                ),
                            'route' => 'work-review.index',
                        ]
                        : null,

                    $workReadyToInvoice->count() > 0
                        ? [
                            'level' => 'red',
                            'title' => 'Create invoices from approved work',
                            'detail' => $workReadyToInvoice->count()
                                .' item(s) · £'
                                .number_format(
                                    (float) $workReadyToInvoice
                                        ->sum('commercial_value'),
                                    2
                                ),
                            'route' => 'work-review.index',
                        ]
                        : null,

                    $draftsAwaitingApproval->count() > 0
                        ? [
                            'level' => 'orange',
                            'title' => 'Review invoice drafts',
                            'detail' => $draftsAwaitingApproval->count()
                                .' draft(s) awaiting approval',
                            'route' => 'billing.review',
                        ]
                        : null,

                    $expensesToReview->count() > 0
                        ? [
                            'level' => 'orange',
                            'title' => 'Review expenses',
                            'detail' => $expensesToReview->count()
                                .' transaction(s) need attention',
                            'route' => 'money-out.index',
                        ]
                        : null,

                    $overdue->count() > 0
                        ? [
                            'level' => 'orange',
                            'title' => 'Chase overdue invoices',
                            'detail' => $overdue->count()
                                .' invoice(s) · £'
                                .number_format(
                                    (float) $overdue
                                        ->sum('outstanding_amount'),
                                    2
                                ),
                            'route' => 'chase.index',
                        ]
                        : null,
                ])
            ),
        ];
    }
}
