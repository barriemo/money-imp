<?php

namespace App\Domains\BusinessBrain\CashTruth;

use App\Domains\FinancialTruth\Services\FinancialTruthService;
use Carbon\CarbonInterface;

class CashTruthService
{
    public function __construct(
        private FinancialTruthService $financialTruth
    ) {}

    public function current(): CashTruth
    {
        $truth =
            $this->financialTruth
                ->build();

        $accounts =
            collect(
                $truth['accounts']
            );

        $verified =
            $accounts
                ->where(
                    'verified',
                    true
                );

        $fresh =
            $verified
                ->filter(
                    function (array $account): bool {
                        $balanceAt =
                            $account['balance_at'];

                        if (! $balanceAt instanceof CarbonInterface) {
                            return false;
                        }

                        return $balanceAt
                            ->greaterThanOrEqualTo(
                                now()->subDays(7)
                            );
                    }
                );

        $accountCount =
            $accounts->count();

        $verifiedAccountCount =
            $verified->count();

        $freshAccountCount =
            $fresh->count();

        $staleAccountCount =
            max(
                0,
                $verifiedAccountCount
                - $freshAccountCount
            );

        $unverified =
            $accounts
                ->where(
                    'verified',
                    false
                );

        $unverifiedAccountCount =
            $unverified->count();

        $reportedAccountingBalance =
            (float) $unverified
                ->where(
                    'type',
                    'StandardBankAccount'
                )
                ->where(
                    'source',
                    'freeagent'
                )
                ->sum(
                    fn (array $account) => (float) (
                        $account['reported_balance']
                        ?? 0
                    )
                );

        $reportedUnverifiedCardDebt =
            abs(
                (float) $unverified
                    ->where(
                        'type',
                        'CreditCardAccount'
                    )
                    ->filter(
                        fn (array $account) => (
                            $account['reported_balance']
                            ?? 0
                        ) < 0
                    )
                    ->sum(
                        fn (array $account) => (float) (
                            $account['reported_balance']
                            ?? 0
                        )
                    )
            );

        $freshnessConfidence =
            $accountCount > 0
                ? (int) round(
                    (
                        $freshAccountCount
                        / $accountCount
                    ) * 100
                )
                : 0;

        $bankVerificationConfidence =
            (int) $truth[
                'confidence'
            ]['bank_balances'];

        $liabilityConfidence =
            (int) $truth[
                'confidence'
            ]['liabilities'];

        $receivableConfidence =
            (int) $truth[
                'confidence'
            ]['receivables'];

        $cashConfidence =
            min(
                $bankVerificationConfidence,
                $freshnessConfidence,
                $liabilityConfidence
            );

        $knownNetPosition =
            (float) $truth[
                'cash'
            ]['net_position'];

        /*
         * "Known net position" is always allowed to exist.
         *
         * "Safe available cash" is a stronger claim and
         * therefore requires complete current bank and
         * liability evidence.
         */
        $safeAvailableCash =
            $cashConfidence === 100
                ? $knownNetPosition
                : null;

        $balanceDates =
            $verified
                ->pluck(
                    'balance_at'
                )
                ->filter()
                ->sort();

        return new CashTruth(
            accountCount: $accountCount,

            verifiedAccountCount: $verifiedAccountCount,

            freshAccountCount: $freshAccountCount,

            staleAccountCount: $staleAccountCount,

            unverifiedAccountCount: $unverifiedAccountCount,

            verifiedCash: (float) $truth[
                'cash'
            ]['available'],

            reportedAccountingBalance: $reportedAccountingBalance,

            reportedUnverifiedCardDebt: $reportedUnverifiedCardDebt,

            creditCardDebt: (float) $truth[
                'cash'
            ]['credit_card_debt'],

            knownLiabilities: (float) $truth[
                'cash'
            ]['known_liabilities'],

            knownNetPosition: $knownNetPosition,

            safeAvailableCash: $safeAvailableCash,

            ledgerReceivables: (float) $truth[
                'receivables'
            ]['ledger_outstanding'],

            paymentsWaitingAllocation: (float) $truth[
                'receivables'
            ]['payments_waiting_allocation'],

            bankVerificationConfidence: $bankVerificationConfidence,

            bankFreshnessConfidence: $freshnessConfidence,

            liabilityConfidence: $liabilityConfidence,

            receivableConfidence: $receivableConfidence,

            cashConfidence: $cashConfidence,

            oldestBalanceAt: $balanceDates
                ->first()?->toIso8601String(),

            newestBalanceAt: $balanceDates
                ->last()?->toIso8601String()
        );
    }
}
