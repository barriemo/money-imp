<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\ObligationTruth\CreditObligationReconciliationService;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CreditFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditObligationReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_payment_is_reconciled_against_bank_evidence(): void
    {
        CreditFacility::create([
            'provider' => 'capital_on_tap',
            'name' => 'Capital on Tap',
            'facility_type' => 'business_credit_card',
            'currency' => 'GBP',
            'reported_balance' => 34351.65,
            'reported_balance_at' => '2026-07-26',
            'minimum_payment' => 3435.16,
            'payment_due_at' => '2026-07-31',
            'verified' => true,
            'confidence' => 100,
            'status' => 'active',
        ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,

            'transaction_date' => '2026-07-31',

            'description' => 'CAPITAL ON TAP',

            'amount' => -3435.16,

            'transaction_hash' => hash(
                'sha256',
                implode(
                    '|',
                    [
                        $account->id,
                        '2026-07-31',
                        'CAPITAL ON TAP',
                        '-3435.16',
                    ]
                )
            ),

            'match_status' => 'unmatched',
        ]);

        $obligation =
            app(
                CreditObligationReconciliationService::class
            )
                ->current()
                ->first();

        $this->assertNotNull(
            $obligation
        );

        $this->assertSame(
            'satisfied',
            $obligation->status
        );

        $this->assertSame(
            3435.16,
            $obligation->matchedPayment
        );

        $this->assertSame(
            '2026-07-31',
            $obligation->matchedAt
        );

        $this->assertSame(
            100,
            $obligation->confidence
        );
    }
}
