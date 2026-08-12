<?php

namespace Tests\Feature;

use App\Domains\FinancialTruth\Services\BankBalanceEvidenceService;
use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankBalanceEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_freeagent_balance_becomes_unverified_snapshot(): void
    {
        $account =
            BankAccount::create([
                'name' => 'Business Current Account',

                'account_type' => 'StandardBankAccount',

                'currency' => 'GBP',

                'status' => 'active',
            ]);

        $snapshot = app(
            BankBalanceEvidenceService::class
        )->capture(
            account: $account,

            balance: 12500,

            balanceAt: now()
                ->subDays(10),

            source: 'freeagent'
        );

        $this->assertFalse(
            $snapshot->verified
        );

        $this->assertSame(
            '12500.00',
            $snapshot->balance
        );

        $this->assertSame(
            80,
            $snapshot->confidence
        );
    }

    public function test_credit_card_balance_is_stored_as_debt(): void
    {
        $account =
            BankAccount::create([
                'name' => 'Credit Card',

                'account_type' => 'CreditCardAccount',

                'currency' => 'GBP',

                'status' => 'active',
            ]);

        $snapshot = app(
            BankBalanceEvidenceService::class
        )->capture(
            account: $account,

            balance: 500,

            balanceAt: now(),

            source: 'freeagent'
        );

        $this->assertSame(
            '-500.00',
            $snapshot->balance
        );

        $this->assertFalse(
            $snapshot->verified
        );
    }

    public function test_same_external_balance_is_idempotent(): void
    {
        $account =
            BankAccount::create([
                'name' => 'Business Current Account',

                'account_type' => 'StandardBankAccount',

                'currency' => 'GBP',

                'status' => 'active',
            ]);

        $at =
            now()
                ->subDay()
                ->startOfSecond();

        $service = app(
            BankBalanceEvidenceService::class
        );

        $service->capture(
            $account,
            1000,
            $at,
            'freeagent'
        );

        $service->capture(
            $account,
            1000,
            $at,
            'freeagent'
        );

        $this->assertDatabaseCount(
            'account_balance_snapshots',
            1
        );
    }
}
