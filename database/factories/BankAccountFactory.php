<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'account_type' => 'StandardBankAccount',
            'currency' => 'GBP',
            'current_balance' => 0,
            'status' => 'active',
        ];
    }
}
