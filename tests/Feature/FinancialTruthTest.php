<?php

namespace Tests\Feature;

use App\Domains\FinancialTruth\Services\FinancialTruthService;
use App\Models\AccountBalanceSnapshot;
use App\Models\BankAccount;
use App\Models\Liability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_verified_balances_are_called_cash(): void
    {
        $current = BankAccount::factory()->create([
            'account_type' => 'StandardBankAccount',
        ]);

        $unverified = BankAccount::factory()->create([
            'account_type' => 'StandardBankAccount',
        ]);

        AccountBalanceSnapshot::create([
            'bank_account_id' => $current->id,

            'balance' => 12000,

            'source' => 'bank_statement',

            'balance_at' => now(),

            'verified' => true,

            'confidence' => 100,
        ]);

        AccountBalanceSnapshot::create([
            'bank_account_id' => $unverified->id,

            'balance' => 150000,

            'source' => 'stale_import',

            'balance_at' => now()->subMonths(3),

            'verified' => false,

            'confidence' => 20,
        ]);

        Liability::create([
            'type' => 'vat',
            'name' => 'VAT',
            'amount' => 8000,
            'status' => 'open',
            'verified' => true,
            'confidence' => 100,
        ]);

        $truth = app(
            FinancialTruthService::class
        )->build();

        $this->assertSame(
            12000.0,
            $truth['cash']['available']
        );

        $this->assertSame(
            8000.0,
            $truth['liabilities']['vat']
        );

        $this->assertSame(
            4000.0,
            $truth['cash']['net_position']
        );
    }
}
