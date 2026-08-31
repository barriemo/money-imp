<?php

namespace App\Domains\BusinessBrain\BankTruth;

use App\Models\AccountBalanceSnapshot;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Carbon\CarbonImmutable;

class BankEvidenceCoverageService
{
    private const CURRENT_TRANSACTION_MAX_AGE_DAYS = 7;

    private const CURRENT_BALANCE_MAX_AGE_DAYS = 7;

    public function statutoryPayments(): BankEvidenceCoverage
    {
        $bankAccountIds = BankAccount::query()
            ->where('status', 'active')
            ->where(
                'account_type',
                'StandardBankAccount'
            )
            ->pluck('id');

        $latestTransaction =
            BankTransaction::query()
                ->whereIn(
                    'bank_account_id',
                    $bankAccountIds
                )
                ->max('transaction_date');

        $latestBalance =
            AccountBalanceSnapshot::query()
                ->whereIn(
                    'bank_account_id',
                    $bankAccountIds
                )
                ->max('balance_at');

        return $this->fromDates(
            latestTransactionDate: $latestTransaction,

            latestBalanceAt: $latestBalance,
        );
    }

    public function fromDates(
        string|\DateTimeInterface|null $latestTransactionDate,
        string|\DateTimeInterface|null $latestBalanceAt,
        string|\DateTimeInterface|null $asOf = null,
    ): BankEvidenceCoverage {
        $asOf = $asOf
            ? CarbonImmutable::parse($asOf)
            : CarbonImmutable::now();

        $transactionDate =
            $latestTransactionDate
                ? CarbonImmutable::parse(
                    $latestTransactionDate
                )
                : null;

        $balanceDate =
            $latestBalanceAt
                ? CarbonImmutable::parse(
                    $latestBalanceAt
                )
                : null;

        $transactionAge =
            $transactionDate
                ? max(
                    0,
                    (int) $transactionDate
                        ->startOfDay()
                        ->diffInDays(
                            $asOf->startOfDay()
                        )
                )
                : null;

        $balanceAge =
            $balanceDate
                ? max(
                    0,
                    (int) $balanceDate
                        ->startOfDay()
                        ->diffInDays(
                            $asOf->startOfDay()
                        )
                )
                : null;

        return new BankEvidenceCoverage(
            latestTransactionDate: $transactionDate?->toDateString(),

            latestBalanceAt: $balanceDate?->toDateTimeString(),

            daysSinceLatestTransaction: $transactionAge,

            daysSinceLatestBalance: $balanceAge,

            transactionEvidenceCurrent: $transactionAge !== null
                && $transactionAge
                    <= self::CURRENT_TRANSACTION_MAX_AGE_DAYS,

            balanceEvidenceCurrent: $balanceAge !== null
                && $balanceAge
                    <= self::CURRENT_BALANCE_MAX_AGE_DAYS,
        );
    }
}
