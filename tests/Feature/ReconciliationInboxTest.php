<?php

namespace Tests\Feature;

use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use App\Models\PaymentIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_remembering_payer_assigns_matching_history(): void
    {
        $user = User::factory()->create();

        $client = Client::create([
            'name' => 'Pacson Limited',
        ]);

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        $first = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-06-18',
            'amount' => 60,
            'description' => 'PACSON LIMITED PACSON FP 18/06/26 0046 123///',
            'match_status' => 'unmatched',
            'source_type' => 'freeagent',
            'transaction_hash' => hash('sha256', 'pacson-one'),
        ]);

        $second = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-05-18',
            'amount' => 60,
            'description' => 'PACSON LIMITED PACSON FP 18/05/26 0046 456///',
            'match_status' => 'unmatched',
            'source_type' => 'freeagent',
            'transaction_hash' => hash('sha256', 'pacson-two'),
        ]);

        $this->actingAs($user)
            ->post(
                route('reconciliation.assign-client', $first),
                [
                    'client_id' => $client->id,
                    'remember_identity' => 1,
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            $client->id,
            $second->refresh()->client_id
        );

        $this->assertSame(
            'suggested',
            $second->match_status
        );

        $this->assertSame(1, PaymentIdentity::count());
    }

    public function test_payment_can_be_manually_allocated_to_client_invoice(): void
    {
        $user = User::factory()->create();

        $client = Client::create([
            'name' => 'Walker',
        ]);

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-500',
            'status' => 'overdue',
            'gross_amount' => 1620,
            'outstanding_amount' => 1620,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-06-05',
            'amount' => 1620,
            'description' => 'WALKER THE JEWELLERS',
            'match_status' => 'suggested',
            'source_type' => 'freeagent',
            'transaction_hash' => hash('sha256', 'walker-payment'),
        ]);

        $this->actingAs($user)
            ->post(
                route('reconciliation.allocate-invoice', $transaction),
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 1620,
                ]
            )
            ->assertRedirect();

        $allocation = PaymentAllocation::firstOrFail();

        $this->assertSame('approved', $allocation->status);
        $this->assertSame('1620.00', $allocation->amount);
        $this->assertSame(
            'reconciled',
            $transaction->refresh()->match_status
        );
    }
}
