<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\InvoicePaymentTruthService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentTruthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_bank_allocation_creates_paid_payment_truth(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Payment Truth Client',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-TRUTH-001',
                'status' => 'paid',
                'gross_amount' => 1200,
                'paid_amount' => 1200,
                'outstanding_amount' => 0,
            ]);

        $transaction =
            $this->incomingTransaction(
                amount: 1200,
                description: 'PAYMENT TRUTH CLIENT'
            );

        PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_invoice_id' => $invoice->id,
            'amount' => 1200,
            'status' => 'approved',
            'confidence' => 100,
            'match_method' => 'manual',
        ]);

        $truth =
            app(
                InvoicePaymentTruthService::class
            )->forInvoice(
                $invoice->load('client')
            );

        $this->assertSame(
            1200.0,
            $truth->bankConfirmedPaid
        );

        $this->assertSame(
            0.0,
            $truth->provenOutstanding
        );

        $this->assertSame(
            'paid',
            $truth->status
        );

        $this->assertFalse(
            $truth->accountingConflict
        );

        $this->assertSame(
            100,
            $truth->confidence
        );
    }

    public function test_suggested_allocation_remains_ambiguous(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Suggested Client',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-TRUTH-002',
                'status' => 'overdue',
                'gross_amount' => 1800,
                'paid_amount' => 0,
                'outstanding_amount' => 1800,
            ]);

        $transaction =
            $this->incomingTransaction(
                amount: 1800,
                description: 'SUGGESTED CLIENT'
            );

        PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_invoice_id' => $invoice->id,
            'amount' => 1800,
            'status' => 'suggested',
            'confidence' => 95,
            'match_method' => 'client_and_exact_amount',
        ]);

        $truth =
            app(
                InvoicePaymentTruthService::class
            )->forInvoice(
                $invoice->load('client')
            );

        $this->assertSame(
            0.0,
            $truth->bankConfirmedPaid
        );

        $this->assertSame(
            1800.0,
            $truth->suggestedPaid
        );

        $this->assertSame(
            'ambiguous',
            $truth->status
        );

        $this->assertSame(
            70,
            $truth->confidence
        );
    }

    public function test_accounting_paid_without_bank_evidence_creates_conflict(): void
    {
        $client =
            Client::factory()->create();

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-TRUTH-003',
                'status' => 'paid',
                'gross_amount' => 2400,
                'paid_amount' => 2400,
                'outstanding_amount' => 0,
            ]);

        $truth =
            app(
                InvoicePaymentTruthService::class
            )->forInvoice(
                $invoice->load('client')
            );

        $this->assertSame(
            0.0,
            $truth->bankConfirmedPaid
        );

        $this->assertSame(
            2400.0,
            $truth->provenOutstanding
        );

        $this->assertSame(
            'unpaid',
            $truth->status
        );

        $this->assertTrue(
            $truth->accountingConflict
        );

        $this->assertSame(
            50,
            $truth->confidence
        );
    }

    private function incomingTransaction(
        float $amount,
        string $description
    ): BankTransaction {
        $account =
            BankAccount::firstOrCreate(
                [
                    'name' => 'Business Current Account',
                ],
                [
                    'account_type' => 'StandardBankAccount',
                    'currency' => 'GBP',
                    'status' => 'active',
                ]
            );

        return BankTransaction::create([
            'bank_account_id' => $account->id,

            'transaction_date' => now(),

            'amount' => $amount,

            'description' => $description,

            'match_status' => 'reconciled',

            'source_type' => 'rbs_pdf',

            'transaction_hash' => hash(
                'sha256',
                $description.'|'.$amount.'|'.microtime(true)
            ),
        ]);
    }
}
