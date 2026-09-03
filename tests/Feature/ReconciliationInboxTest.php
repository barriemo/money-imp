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

    public function test_ready_queue_explains_priority_without_auto_approving(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Priority Client',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',

                'account_type' => 'StandardBankAccount',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'INV-PRIORITY-1',

                'status' => 'overdue',

                'gross_amount' => 120,

                'outstanding_amount' => 120,
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => '2026-06-01',

                'amount' => 120,

                'description' => 'PRIORITY CLIENT INV-PRIORITY-1',

                'transaction_type' => 'customer_payment',

                'match_status' => 'suggested',

                'match_confidence' => 100,

                'source_type' => 'freeagent',

                'transaction_hash' => hash(
                    'sha256',
                    'priority-inbox-display'
                ),
            ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,

            'accounting_invoice_id' => $invoice->id,

            'amount' => 120,

            'status' => 'suggested',

            'confidence' => 100,

            'match_method' => 'client_and_invoice_reference',
        ]);

        $this->actingAs(
            $user
        )
            ->get(
                route(
                    'reconciliation.index',
                    [
                        'tab' => 'ready',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Human review queue.'
            )
            ->assertSee(
                'Strong review'
            )
            ->assertSee(
                'Review priority 80/100'
            )
            ->assertSee(
                'Invoice reference matched the bank transaction.'
            )
            ->assertSee(
                'Engine confidence is shown as source metadata'
            );

        $this->assertSame(
            'suggested',
            PaymentAllocation::query()
                ->sole()
                ->status
        );
    }

    public function test_ready_suggestion_can_be_approved_by_human(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Approve Priority Client',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',

                'account_type' => 'StandardBankAccount',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'INV-APPROVE-1',

                'status' => 'overdue',

                'gross_amount' => 180,

                'outstanding_amount' => 180,
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => '2026-06-01',

                'amount' => 180,

                'description' => 'APPROVE PRIORITY CLIENT',

                'transaction_type' => 'customer_payment',

                'match_status' => 'suggested',

                'match_confidence' => 100,

                'source_type' => 'freeagent',

                'transaction_hash' => hash(
                    'sha256',
                    'priority-inbox-approve'
                ),
            ]);

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => 180,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'client_and_exact_amount',
            ]);

        $this->actingAs(
            $user
        )
            ->post(
                route(
                    'reconciliation.suggestions.approve',
                    $allocation
                )
            )
            ->assertRedirect();

        $this->assertSame(
            'approved',
            $allocation
                ->fresh()
                ->status
        );

        $this->assertSame(
            'reconciled',
            $transaction
                ->fresh()
                ->match_status
        );

        $this->assertSame(
            (string) $user->id,
            (string) $transaction
                ->fresh()
                ->matched_by
        );
    }

    public function test_stale_ready_suggestion_cannot_be_approved_but_can_be_rejected(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Stale Priority Client',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',

                'account_type' => 'StandardBankAccount',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'INV-STALE-1',

                'status' => 'paid',

                'gross_amount' => 360,

                'paid_amount' => 360,

                'outstanding_amount' => 0,
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => '2026-06-01',

                'amount' => 360,

                'description' => 'STALE PRIORITY CLIENT',

                'transaction_type' => 'customer_payment',

                'match_status' => 'suggested',

                'match_confidence' => 100,

                'source_type' => 'freeagent',

                'transaction_hash' => hash(
                    'sha256',
                    'priority-inbox-stale'
                ),
            ]);

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => 360,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'canonical_client_exact_amount',
            ]);

        $this->actingAs(
            $user
        )
            ->post(
                route(
                    'reconciliation.suggestions.approve',
                    $allocation
                )
            )
            ->assertRedirect()
            ->assertSessionHas(
                'error'
            );

        $this->assertSame(
            'suggested',
            $allocation
                ->fresh()
                ->status
        );

        $this->actingAs(
            $user
        )
            ->post(
                route(
                    'reconciliation.suggestions.reject',
                    $allocation
                )
            )
            ->assertRedirect();

        $this->assertSame(
            'rejected',
            $allocation
                ->fresh()
                ->status
        );

        /*
         * Rejecting the invoice suggestion does not silently
         * classify or discard the receipt itself.
         */
        $this->assertSame(
            'suggested',
            $transaction
                ->fresh()
                ->match_status
        );

        $this->assertSame(
            $client->id,
            $transaction
                ->fresh()
                ->client_id
        );
    }
}
