<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Reconciliation\UniqueExactPaymentMatchService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueExactPaymentMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_canonical_payment_creates_suggested_invoice_match(): void
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

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-UNIQUE-001',
            'invoice_date' => '2026-01-01',
            'status' => 'paid',
            'gross_amount' => 1250,
            'paid_amount' => 1250,
            'outstanding_amount' => 0,
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-01-10',
            'amount' => 1250,
            'description' => 'CUSTOMER PAYMENT',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'unique-match'
            ),
        ]);

        $stats =
            app(
                UniqueExactPaymentMatchService::class
            )->generate();

        $this->assertSame(
            1,
            $stats['created']
        );

        $this->assertSame(
            1250.0,
            $stats['value']
        );

        $allocation =
            PaymentAllocation::firstOrFail();

        $this->assertSame(
            'suggested',
            $allocation->status
        );

        $this->assertSame(
            'canonical_client_exact_amount',
            $allocation->match_method
        );
    }
}
