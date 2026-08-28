<?php

namespace App\Domains\FinancialTruth\Services;

use App\Models\AccountBalanceSnapshot;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\Liability;
use App\Models\PaymentAllocation;

class FinancialTruthService
{
    public function build(): array
    {
        $accounts = BankAccount::query()
            ->orderBy('name')
            ->get();

        $accountTruth = $accounts->map(
            function (BankAccount $account): array {
                $reportedSnapshot = AccountBalanceSnapshot::query()
                    ->where(
                        'bank_account_id',
                        $account->id
                    )
                    ->latest('balance_at')
                    ->first();

                $snapshot = AccountBalanceSnapshot::query()
                    ->where(
                        'bank_account_id',
                        $account->id
                    )
                    ->where(
                        'verified',
                        true
                    )
                    ->whereIn(
                        'source',
                        [
                            'open_banking',
                            'bank_statement',
                            'bank_balance_export',
                        ]
                    )
                    ->latest(
                        'balance_at'
                    )
                    ->first();

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->account_type,

                    /*
                     * balance is trusted Financial Truth.
                     *
                     * reported_balance is evidence we know about
                     * but have not necessarily verified.
                     */
                    'balance' => $snapshot
                        ? (float) $snapshot->balance
                        : null,

                    'balance_at' => $snapshot?->balance_at,

                    'reported_balance' => $reportedSnapshot
                        ? (float) $reportedSnapshot->balance
                        : null,

                    'reported_balance_at' => $reportedSnapshot?->balance_at,

                    'verified' => $snapshot !== null,

                    'confidence' => $reportedSnapshot?->confidence ?? 0,

                    'source' => $reportedSnapshot?->source,
                ];
            }
        );

        $cash = $accountTruth
            ->where(
                'type',
                'StandardBankAccount'
            )
            ->where('verified', true)
            ->sum('balance');

        $cardDebt = abs(
            $accountTruth
                ->where(
                    'type',
                    'CreditCardAccount'
                )
                ->where('verified', true)
                ->filter(
                    fn (array $account) => $account['balance'] < 0
                )
                ->sum('balance')
        );

        $liabilities = Liability::query()
            ->where('status', 'open')
            ->get();

        $verifiedLiabilities = $liabilities
            ->where('verified', true);

        /*
         * Do not call stale invoice ledger values
         * collectible cash.
         *
         * This is the accounting ledger position only.
         */
        $ledgerReceivables =
            (float) AccountingInvoice::query()
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                )
                ->sum(
                    'outstanding_amount'
                );

        $suggestedAllocations =
            (float) PaymentAllocation::query()
                ->where(
                    'status',
                    'suggested'
                )
                ->sum('amount');

        $knownLiabilities =
            (float) $verifiedLiabilities
                ->sum('amount');

        $verifiedAccountCount =
            $accountTruth
                ->where('verified', true)
                ->count();

        $totalAccountCount =
            $accountTruth->count();

        $balanceConfidence =
            $totalAccountCount > 0
                ? (int) round(
                    (
                        $verifiedAccountCount
                        / $totalAccountCount
                    ) * 100
                )
                : 0;

        return [
            'accounts' => $accountTruth->values(),

            'cash' => [
                'available' => $cash,

                'credit_card_debt' => $cardDebt,

                'known_liabilities' => $knownLiabilities,

                'net_position' => $cash
                    - $cardDebt
                    - $knownLiabilities,

                'confidence' => $balanceConfidence,
            ],

            'receivables' => [
                'ledger_outstanding' => $ledgerReceivables,

                'payments_waiting_allocation' => $suggestedAllocations,

                /*
                 * Deliberately not pretending
                 * this is "true debtors" yet.
                 */
                'verified_collectible' => null,

                'confidence' => 0,
            ],

            'liabilities' => [
                'total' => $knownLiabilities,

                'vat' => (float)
                    $verifiedLiabilities
                        ->where(
                            'type',
                            'vat'
                        )
                        ->sum('amount'),

                'paye' => (float)
                    $verifiedLiabilities
                        ->where(
                            'type',
                            'paye'
                        )
                        ->sum('amount'),

                'employer_nic' => (float)
                    $verifiedLiabilities
                        ->whereIn(
                            'type',
                            [
                                'employer_nic',
                                'employer_national_insurance',
                                'nic',
                            ]
                        )
                        ->sum('amount'),

                'payroll' => (float)
                    $verifiedLiabilities
                        ->whereIn(
                            'type',
                            [
                                'payroll',
                                'wages',
                                'salary',
                            ]
                        )
                        ->sum('amount'),

                'other' => (float)
                    $verifiedLiabilities
                        ->whereNotIn(
                            'type',
                            [
                                'vat',
                                'paye',
                                'employer_nic',
                                'employer_national_insurance',
                                'nic',
                                'payroll',
                                'wages',
                                'salary',
                            ]
                        )
                        ->sum('amount'),

                'coverage' => [
                    'record_count' => $liabilities->count(),
                    'verified_record_count' => $verifiedLiabilities->count(),

                    'records_complete' => $liabilities->count() > 0
                        && $verifiedLiabilities->count() === $liabilities->count(),

                    'complete' => false,

                    'categories' => [
                        'vat' => $verifiedLiabilities
                            ->contains(
                                fn ($liability) => $liability->type === 'vat'
                            ),

                        'paye' => $verifiedLiabilities
                            ->contains(
                                fn ($liability) => $liability->type === 'paye'
                            ),

                        'employer_nic' => $verifiedLiabilities
                            ->contains(
                                fn ($liability) => in_array(
                                    $liability->type,
                                    [
                                        'employer_nic',
                                        'employer_national_insurance',
                                        'nic',
                                    ],
                                    true
                                )
                            ),

                        'payroll' => $verifiedLiabilities
                            ->contains(
                                fn ($liability) => in_array(
                                    $liability->type,
                                    [
                                        'payroll',
                                        'wages',
                                        'salary',
                                    ],
                                    true
                                )
                            ),

                        'other' => $verifiedLiabilities
                            ->contains(
                                fn ($liability) => ! in_array(
                                    $liability->type,
                                    [
                                        'vat',
                                        'paye',
                                        'employer_nic',
                                        'employer_national_insurance',
                                        'nic',
                                        'payroll',
                                        'wages',
                                        'salary',
                                    ],
                                    true
                                )
                            ),
                    ],
                ],
            ],

            'confidence' => [
                'bank_balances' => $balanceConfidence,

                'liabilities' => $liabilities->count() > 0
                        ? (int) round(
                            (
                                $verifiedLiabilities
                                    ->count()
                                / $liabilities->count()
                            ) * 100
                        )
                        : 0,

                'receivables' => 0,
            ],
        ];
    }
}
