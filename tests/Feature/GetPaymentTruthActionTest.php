<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Actions\GetPaymentTruthAction;
use App\Domains\BusinessBrain\Insights\BusinessInsight;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetPaymentTruthActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_brain_can_return_payment_truth_insight(): void
    {
        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-06-01',
            'amount' => 500,
            'description' => 'CUSTOMER PAYMENT',
            'transaction_type' => 'customer_payment',
            'source_type' => 'rbs_pdf',
            'transaction_hash' => hash(
                'sha256',
                'business-brain-payment'
            ),
        ]);

        $insight =
            app(
                GetPaymentTruthAction::class
            )->execute();

        $this->assertInstanceOf(
            BusinessInsight::class,
            $insight
        );

        $this->assertSame(
            'Customer Payment Truth',
            $insight->headline
        );

        $this->assertSame(
            'needs_attention',
            $insight->status
        );

        $this->assertSame(
            '£500.00',
            $insight->metrics['received']
        );

        $this->assertSame(
            '£500.00',
            $insight->metrics['unmatched']
        );

        $this->assertNotEmpty(
            $insight->actions
        );
    }
}
