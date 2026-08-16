<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Position\PaymentTruthPositionService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTruthPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_truth_position_separates_allocated_and_unallocated_customer_cash(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Test Customer',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        $allocatedTransaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'client_id' => $client->id,
                'transaction_date' => '2026-06-01',
                'amount' => 1000,
                'description' => 'ALLOCATED CUSTOMER',
                'transaction_type' => 'customer_payment',
                'source_type' => 'rbs_pdf',
                'transaction_hash' => hash(
                    'sha256',
                    'allocated'
                ),
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-001',
                'status' => 'paid',
                'gross_amount' => 1000,
                'paid_amount' => 1000,
                'outstanding_amount' => 0,
            ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $allocatedTransaction->id,
            'accounting_invoice_id' => $invoice->id,
            'amount' => 1000,
            'status' => 'approved',
            'confidence' => 100,
            'match_method' => 'manual',
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-06-02',
            'amount' => 500,
            'description' => 'UNKNOWN CUSTOMER',
            'transaction_type' => 'customer_payment',
            'source_type' => 'rbs_pdf',
            'transaction_hash' => hash(
                'sha256',
                'unallocated'
            ),
        ]);

        $position =
            app(
                PaymentTruthPositionService::class
            )->current();

        $this->assertSame(
            1500.0,
            $position->canonicalReceived
        );

        $this->assertSame(
            1000.0,
            $position->allocatedReceived
        );

        $this->assertSame(
            500.0,
            $position->unmatchedReceived
        );

        $this->assertSame(
            0.0,
            $position->suggestedReceived
        );

        $this->assertSame(
            2,
            $position->paymentCount
        );

        $this->assertSame(
            1,
            $position->allocatedPaymentCount
        );

        $this->assertSame(
            0,
            $position->suggestedPaymentCount
        );

        $this->assertSame(
            1,
            $position->unmatchedPaymentCount
        );
    }
}
