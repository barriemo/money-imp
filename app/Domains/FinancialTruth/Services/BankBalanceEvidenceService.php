<?php

namespace App\Domains\FinancialTruth\Services;

use App\Models\AccountBalanceSnapshot;
use App\Models\BankAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class BankBalanceEvidenceService
{
    public function capture(
        BankAccount $account,
        float $balance,
        CarbonInterface|string $balanceAt,
        string $source = 'freeagent'
    ): AccountBalanceSnapshot {
        $balanceAt =
            $balanceAt instanceof CarbonInterface
                ? $balanceAt
                : Carbon::parse(
                    $balanceAt
                );

        /*
         * Financial Truth represents credit-card debt
         * as a negative account balance.
         */
        if (
            $account->account_type
            === 'CreditCardAccount'
        ) {
            $balance =
                -abs(
                    $balance
                );
        }

        return AccountBalanceSnapshot::updateOrCreate(
            [
                'bank_account_id' => $account->id,

                'source' => $source,

                'balance_at' => $balanceAt,
            ],
            [
                'balance' => $balance,

                'verified' => false,

                'confidence' => $this->confidence(
                    $balanceAt
                ),

                'notes' => 'Imported balance evidence. Requires verification before Financial Truth treats it as cash.',

                'metadata' => [
                'requires_verification' => true,
                ],
            ]
        );
    }

    private function confidence(
        CarbonInterface $balanceAt
    ): int {
        $days =
            $balanceAt
                ->diffInDays(
                    now()
                );

        return match (true) {
            $days <= 7 => 90,

            $days <= 31 => 80,

            $days <= 90 => 60,

            $days <= 180 => 40,

            default => 20,
        };
    }
}
