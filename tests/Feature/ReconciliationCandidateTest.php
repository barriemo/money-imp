<?php

namespace Tests\Feature;

use App\Domains\Reconciliation\Services\ReconciliationCandidateService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationCandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_name_and_exact_invoice_amount_create_suggestion(): void
    {
        $client = Client::create([
            'name' => 'Broker Insights',
        ]);

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-100',
            'status' => 'overdue',
            'gross_amount' => 180,
            'outstanding_amount' => 180,
        ]);

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-05-28',
            'amount' => 180,
            'description' => 'BROKER INSIGHTS LIBROKER INSIGHTS',
            'match_status' => 'unmatched',
            'source_type' => 'freeagent',
            'transaction_hash' => hash('sha256', 'broker-test'),
        ]);

        app(ReconciliationCandidateService::class)->generate();

        $transaction->refresh();

        $this->assertSame($client->id, $transaction->client_id);
        $this->assertSame('suggested', $transaction->match_status);
        $this->assertSame('customer_payment', $transaction->transaction_type);

        $allocation = PaymentAllocation::firstOrFail();

        $this->assertSame($invoice->id, $allocation->accounting_invoice_id);
        $this->assertSame('180.00', $allocation->amount);
        $this->assertSame('suggested', $allocation->status);
    }

    public function test_internal_transfer_is_not_matched_to_client_invoice(): void
    {
        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-05-20',
            'amount' => 2000,
            'description' => 'From A/C 00293009 CERULEAN DAZE STUD Via Mobile Xfer',
            'match_status' => 'unmatched',
            'source_type' => 'freeagent',
            'transaction_hash' => hash('sha256', 'transfer-test'),
        ]);

        app(ReconciliationCandidateService::class)->generate();

        $transaction->refresh();

        $this->assertNull($transaction->client_id);
        $this->assertSame('internal_transfer', $transaction->transaction_type);
        $this->assertSame('ignored', $transaction->match_status);
        $this->assertSame(0, PaymentAllocation::count());
    }

    public function test_rebuilding_candidates_is_idempotent(): void
    {
        $client = Client::create([
            'name' => 'Broker Insights',
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-200',
            'status' => 'overdue',
            'invoice_date' => '2026-05-01',
            'gross_amount' => 180,
            'outstanding_amount' => 180,
        ]);

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-05-28',
            'amount' => 180,
            'description' => 'BROKER INSIGHTS',
            'match_status' => 'unmatched',
            'source_type' => 'freeagent',
            'transaction_hash' => hash('sha256', 'repeat-test'),
        ]);

        $this->artisan('money-imp:reconciliation-candidates')
            ->assertSuccessful();

        $firstSuggested = BankTransaction::where('match_status', 'suggested')->count();
        $firstAllocations = PaymentAllocation::where('status', 'suggested')->count();

        $this->artisan('money-imp:reconciliation-candidates')
            ->assertSuccessful();

        $this->assertSame(
            $firstSuggested,
            BankTransaction::where('match_status', 'suggested')->count()
        );

        $this->assertSame(
            $firstAllocations,
            PaymentAllocation::where('status', 'suggested')->count()
        );
    }
}
