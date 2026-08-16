<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Reconciliation\ReconciliationSummaryService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_summary_separates_confirmed_suggested_and_ignored_money(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Reconciliation Client',
            ]);

        $confirmedInvoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-R-001',
                'gross_amount' => 1200,
                'outstanding_amount' => 1200,
            ]);

        $suggestedInvoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-R-002',
                'gross_amount' => 600,
                'outstanding_amount' => 600,
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        $confirmed =
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'transaction_date' => now(),
                'amount' => 1200,
                'description' => 'RECONCILIATION CLIENT',
                'transaction_type' => 'customer_payment',
                'match_status' => 'reconciled',
                'source_type' => 'rbs_pdf',
                'transaction_hash' => hash(
                    'sha256',
                    'reconciliation-confirmed'
                ),
            ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $confirmed->id,
            'accounting_invoice_id' => $confirmedInvoice->id,
            'amount' => 1200,
            'status' => 'approved',
            'confidence' => 100,
        ]);

        $suggested =
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'transaction_date' => now(),
                'amount' => 600,
                'description' => 'RECONCILIATION CLIENT',
                'transaction_type' => 'customer_payment',
                'match_status' => 'suggested',
                'source_type' => 'rbs_pdf',
                'transaction_hash' => hash(
                    'sha256',
                    'reconciliation-suggested'
                ),
            ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $suggested->id,
            'accounting_invoice_id' => $suggestedInvoice->id,
            'amount' => 600,
            'status' => 'suggested',
            'confidence' => 95,
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => now(),
            'amount' => 5000,
            'description' => 'INTERNAL TRANSFER',
            'transaction_type' => 'director_or_internal_transfer',
            'match_status' => 'ignored',
            'source_type' => 'rbs_pdf',
            'transaction_hash' => hash(
                'sha256',
                'reconciliation-ignored'
            ),
        ]);

        $summary =
            app(
                ReconciliationSummaryService::class
            )->current();

        $this->assertSame(
            2,
            $summary->customerPaymentCount
        );

        $this->assertSame(
            1800.0,
            $summary->customerPaymentValue
        );

        $this->assertSame(
            1,
            $summary->confirmedAllocationCount
        );

        $this->assertSame(
            1200.0,
            $summary->confirmedAllocationValue
        );

        $this->assertSame(
            1,
            $summary->suggestedAllocationCount
        );

        $this->assertSame(
            600.0,
            $summary->suggestedAllocationValue
        );

        $this->assertSame(
            5000.0,
            $summary->ignoredTransactionValue
        );

        $this->assertSame(
            50,
            $summary->reconciliationCoverage
        );
    }
}
