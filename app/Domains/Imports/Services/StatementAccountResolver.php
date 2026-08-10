<?php

namespace App\Domains\Imports\Services;

use App\Models\BankAccount;

class StatementAccountResolver
{
    public function resolve(
        string $provider
    ): ?BankAccount {
        return match ($provider) {
            'rbs_pdf' => BankAccount::query()
                ->where('name', 'Business Current Account')
                ->first(),

            'capital_on_tap_pdf' => BankAccount::query()->firstOrCreate(
                [
                    'name' => 'Capital on Tap',
                ],
                [
                    'account_type' => 'CreditCardAccount',

                    'currency' => 'GBP',

                    'current_balance' => 0,

                    'status' => 'active',
                ]
            ),

            default => null,
        };
    }
}
