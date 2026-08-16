<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\PaymentTruthSummaryService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTruthSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_truth_summary_separates_bank_truth_from_accounting_truth(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Summary Client',
            ]);

        $paid =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-SUM-001',
                'status' => 'paid',
                'gross_amount' => 1200,
                'paid_amount' => 1200,
                'outstanding_amount' => 0,
            ]);

        $unsupported =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-SUM-002',
                'status' => 'paid',
                'gross_amount' => 2400,
                'paid_amount' => 2400,
                'outstanding_amount' => 0,
            ]);

        $unpaid =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-SUM-003',
                'status' => 'overdue',
                'gross_amount' => 600,
                'paid_amount' => 0,
                'outstanding_amount' => 600,
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'transaction_date' => now(),
                'amount' => 1200,
                'description' => 'SUMMARY CLIENT',
                'match_status' => 'reconciled',
                'source_type' => 'rbs_pdf',
                'transaction_hash' => hash(
                    'sha256',
                    'summary-approved'
                ),
            ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_invoice_id' => $paid->id,
            'amount' => 1200,
            'status' => 'approved',
            'confidence' => 100,
            'match_method' => 'manual',
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => now(),
            'amount' => 300,
            'description' => 'UNALLOCATED RECEIPT',
            'transaction_type' => 'customer_payment',
            'match_status' => 'unmatched',
            'source_type' => 'rbs_pdf',
            'transaction_hash' => hash(
                'sha256',
                'summary-unallocated'
            ),
        ]);

        $summary =
            app(
                PaymentTruthSummaryService::class
            )->current();

        $this->assertSame(
            3,
            $summary->invoiceCount
        );

        $this->assertSame(
            4200.0,
            $summary->totalInvoiced
        );

        $this->assertSame(
            1200.0,
            $summary->bankConfirmedReceived
        );

        $this->assertSame(
            3000.0,
            $summary->provenOutstanding
        );

        $this->assertSame(
            3600.0,
            $summary->accountingReportedPaid
        );

        $this->assertSame(
            2400.0,
            $summary->accountingConflictValue
        );

        $this->assertSame(
            1,
            $summary->accountingConflictCount
        );

        $this->assertSame(
            300.0,
            $summary->unallocatedIncomingCash
        );
    }
}
