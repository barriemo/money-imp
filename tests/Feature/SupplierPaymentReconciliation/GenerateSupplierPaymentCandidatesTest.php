<?php

namespace Tests\Feature\SupplierPaymentReconciliation;

use App\Models\AccountingBill;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Supplier;
use App\Models\SupplierAlias;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateSupplierPaymentCandidatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_supplier_payment_candidates(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => '20i Limited',
        ]);

        SupplierAlias::create([
            'supplier_id' => $supplier->id,
            'alias' => '20I LIMITED HOSTING',
            'normalised_alias' => '20i limited hosting',
            'confidence' => 100,
        ]);

        $bill = AccountingBill::create([
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'bill_date' => '2026-08-01',
            'currency' => 'GBP',
            'net_amount' => 60,
            'tax_amount' => 0,
            'gross_amount' => 60,
            'paid_amount' => 0,
            'outstanding_amount' => 60,
        ]);

        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-05',
            'amount' => -60,
            'currency' => 'GBP',
            'description' => '20I LIMITED HOSTING',
            'transaction_hash' => hash(
                'sha256',
                'supplier-payment-command-20i-60'
            ),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('money-imp:supplier-payment-candidates')
            ->expectsOutput('Generating supplier payment candidates...')
            ->assertExitCode(0);

        $this->assertDatabaseHas(
            'supplier_payment_allocations',
            [
                'bank_transaction_id' => $transaction->id,
                'accounting_bill_id' => $bill->id,
                'status' => 'suggested',
                'amount' => 60,
            ]
        );
    }

    public function test_command_does_not_overwrite_approved_allocation(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => '20i Limited',
        ]);

        SupplierAlias::create([
            'supplier_id' => $supplier->id,
            'alias' => '20I LIMITED HOSTING',
            'normalised_alias' => '20i limited hosting',
            'confidence' => 100,
        ]);

        $bill = AccountingBill::create([
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'bill_date' => '2026-08-01',
            'currency' => 'GBP',
            'net_amount' => 60,
            'tax_amount' => 0,
            'gross_amount' => 60,
            'paid_amount' => 60,
            'outstanding_amount' => 0,
        ]);

        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-05',
            'amount' => -60,
            'currency' => 'GBP',
            'description' => '20I LIMITED HOSTING',
            'transaction_hash' => hash(
                'sha256',
                'supplier-payment-command-approved-20i-60'
            ),
            'match_status' => 'reconciled',
        ]);

        $allocation = SupplierPaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_bill_id' => $bill->id,
            'amount' => 60,
            'status' => 'approved',
            'confidence' => 100,
        ]);

        $this->artisan('money-imp:supplier-payment-candidates')
            ->assertExitCode(0);

        $this->assertDatabaseHas(
            'supplier_payment_allocations',
            [
                'id' => $allocation->id,
                'amount' => 60,
                'status' => 'approved',
            ]
        );

        $this->assertDatabaseCount(
            'supplier_payment_allocations',
            1
        );
    }
}
