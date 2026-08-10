<?php

namespace Tests\Feature;

use App\Domains\Imports\Services\StatementAccountResolver;
use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatementAccountResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbs_maps_to_business_current_account(): void
    {
        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
            'currency' => 'GBP',
            'current_balance' => 0,
            'status' => 'active',
        ]);

        $resolved = app(
            StatementAccountResolver::class
        )->resolve('rbs_pdf');

        $this->assertNotNull($resolved);

        $this->assertSame(
            $account->id,
            $resolved->id
        );
    }

    public function test_capital_on_tap_account_is_created(): void
    {
        $resolved = app(
            StatementAccountResolver::class
        )->resolve(
            'capital_on_tap_pdf'
        );

        $this->assertNotNull($resolved);

        $this->assertSame(
            'Capital on Tap',
            $resolved->name
        );

        $this->assertSame(
            'CreditCardAccount',
            $resolved->account_type
        );
    }

    public function test_ambiguous_provider_is_left_for_review(): void
    {
        $resolved = app(
            StatementAccountResolver::class
        )->resolve(
            'amex_pdf'
        );

        $this->assertNull($resolved);
    }
}
