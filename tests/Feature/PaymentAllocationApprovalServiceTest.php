<?php

namespace Tests\Feature;

use App\Domains\Accounting\Services\InvoiceBalanceService;
use App\Domains\Reconciliation\Services\PaymentAllocationApprovalService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAllocationApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggested_allocation_can_be_approved(): void
    {
        $user = User::factory()->create();

        $client = Client::create([
            'name' => 'Approval Client',
        ]);

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-APPROVE',
            'status' => 'overdue',
            'gross_amount' => 600,
            'outstanding_amount' => 600,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-08-28',
            'amount' => 600,
            'description' => 'APPROVAL CLIENT',
            'match_status' => 'suggested',
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                'approval-payment'
            ),
        ]);

        $allocation = PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_invoice_id' => $invoice->id,
            'amount' => 600,
            'status' => 'suggested',
            'confidence' => 100,
            'match_method' => 'client_and_exact_amount',
            'reason' => 'Exact amount match.',
        ]);

        app(PaymentAllocationApprovalService::class)
            ->approve($allocation, $user->id);

        $this->assertSame(
            'approved',
            $allocation->refresh()->status
        );

        $this->assertSame(
            '600.00',
            $allocation->amount
        );

        $this->assertSame(
            'reconciled',
            $transaction->refresh()->match_status
        );

        $this->assertSame(
            '0.00',
            app(InvoiceBalanceService::class)->outstanding($invoice->refresh())
        );
    }

    public function test_rejected_suggestion_does_not_become_financial_truth(): void
    {
        $user = User::factory()->create();

        $client = Client::create([
            'name' => 'Reject Client',
        ]);

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-REJECT',
            'status' => 'overdue',
            'gross_amount' => 300,
            'outstanding_amount' => 300,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-08-28',
            'amount' => 300,
            'description' => 'REJECT CLIENT',
            'match_status' => 'suggested',
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                'reject-payment'
            ),
        ]);

        $allocation = PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_invoice_id' => $invoice->id,
            'amount' => 300,
            'status' => 'suggested',
            'confidence' => 100,
            'match_method' => 'client_and_exact_amount',
        ]);

        app(PaymentAllocationApprovalService::class)
            ->reject(
                $allocation,
                $user->id,
                'Wrong customer.'
            );

        $this->assertSame(
            'rejected',
            $allocation->refresh()->status
        );

        $this->assertSame(
            '300.00',
            app(InvoiceBalanceService::class)->outstanding($invoice->refresh())
        );

        $this->assertSame(
            'suggested',
            $transaction->refresh()->match_status
        );
    }
}
