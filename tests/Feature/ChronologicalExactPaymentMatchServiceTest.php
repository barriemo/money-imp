<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Reconciliation\ChronologicalExactPaymentMatchService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChronologicalExactPaymentMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ambiguous_exact_payment_is_suggested_when_only_one_invoice_remains_without_payment_evidence(): void
    {
        $client =
            Client::factory()->create();

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        $first =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-OLD-001',
                'invoice_date' => '2026-01-01',
                'status' => 'paid',
                'gross_amount' => 180,
                'paid_amount' => 180,
                'outstanding_amount' => 0,
            ]);

        $second =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-OLD-002',
                'invoice_date' => '2026-02-01',
                'status' => 'paid',
                'gross_amount' => 180,
                'paid_amount' => 180,
                'outstanding_amount' => 0,
            ]);

        $existingTransaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'client_id' => $client->id,
                'transaction_date' => '2026-01-10',
                'amount' => 180,
                'description' => 'CUSTOMER PAYMENT',
                'transaction_type' => 'customer_payment',
                'source_type' => 'file_import',
                'transaction_hash' => hash(
                    'sha256',
                    'existing-payment'
                ),
            ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $existingTransaction->id,
            'accounting_invoice_id' => $first->id,
            'amount' => 180,
            'status' => 'suggested',
            'confidence' => 100,
            'match_method' => 'existing',
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-02-10',
            'amount' => 180,
            'description' => 'CUSTOMER PAYMENT',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'new-payment'
            ),
        ]);

        $stats =
            app(
                ChronologicalExactPaymentMatchService::class
            )->generate();

        $this->assertSame(
            1,
            $stats['created']
        );

        $this->assertSame(
            180.0,
            $stats['value']
        );

        $allocation =
            PaymentAllocation::query()
                ->where(
                    'accounting_invoice_id',
                    $second->id
                )
                ->firstOrFail();

        $this->assertSame(
            'canonical_chronological_exact_amount',
            $allocation->match_method
        );
    }
}
