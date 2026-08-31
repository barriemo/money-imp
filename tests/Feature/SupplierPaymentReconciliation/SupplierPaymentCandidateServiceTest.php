<?php

namespace Tests\Feature\SupplierPaymentReconciliation;

use App\Domains\Suppliers\Payments\Services\SupplierPaymentCandidateService;
use App\Models\AccountingBill;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Supplier;
use App\Models\SupplierAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPaymentCandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_supplier_payment_creates_bill_suggestion(): void
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
                'supplier-payment-20i-60'
            ),
            'match_status' => 'unmatched',
        ]);

        $stats = app(
            SupplierPaymentCandidateService::class
        )->generate();

        $this->assertSame(1, $stats['supplier_matches']);
        $this->assertSame(1, $stats['bill_matches']);

        $this->assertDatabaseHas(
            'supplier_payment_allocations',
            [
                'bank_transaction_id' => $transaction->id,
                'accounting_bill_id' => $bill->id,
                'status' => 'suggested',
                'amount' => 60,
                'match_method' => 'exact_amount',
            ]
        );
    }

    public function test_approved_supplier_allocation_survives_candidate_regeneration(): void
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
                'supplier-payment-approved-lifecycle'
            ),
            'match_status' => 'unmatched',
        ]);

        $allocation = $transaction->supplierPaymentAllocations()->create([
            'accounting_bill_id' => $bill->id,
            'amount' => 60,
            'status' => 'approved',
            'confidence' => 100,
        ]);

        app(SupplierPaymentCandidateService::class)->generate();

        $this->assertDatabaseHas(
            'supplier_payment_allocations',
            [
                'id' => $allocation->id,
                'status' => 'approved',
                'amount' => 60,
            ]
        );
    }

    public function test_rejected_supplier_allocation_survives_candidate_regeneration(): void
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
                'supplier-payment-rejected-lifecycle'
            ),
            'match_status' => 'unmatched',
        ]);

        $allocation = $transaction->supplierPaymentAllocations()->create([
            'accounting_bill_id' => $bill->id,
            'amount' => 60,
            'status' => 'rejected',
            'confidence' => 100,
        ]);

        app(SupplierPaymentCandidateService::class)->generate();

        $this->assertDatabaseHas(
            'supplier_payment_allocations',
            [
                'id' => $allocation->id,
                'status' => 'rejected',
                'amount' => 60,
            ]
        );
    }

    public function test_candidate_respects_remaining_supplier_payment_amount(): void
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
            'net_amount' => 100,
            'tax_amount' => 0,
            'gross_amount' => 100,
            'paid_amount' => 0,
            'outstanding_amount' => 100,
        ]);

        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-05',
            'amount' => -100,
            'currency' => 'GBP',
            'description' => '20I LIMITED HOSTING',
            'transaction_hash' => hash(
                'sha256',
                'supplier-payment-remaining-balance'
            ),
            'match_status' => 'unmatched',
        ]);

        $transaction->supplierPaymentAllocations()->create([
            'accounting_bill_id' => $bill->id,
            'amount' => 60,
            'status' => 'approved',
            'confidence' => 100,
        ]);

        app(SupplierPaymentCandidateService::class)->generate();

        $this->assertDatabaseHas(
            'supplier_payment_allocations',
            [
                'bank_transaction_id' => $transaction->id,
                'accounting_bill_id' => $bill->id,
                'amount' => 40,
                'status' => 'suggested',
            ]
        );
    }

    public function test_reconciled_supplier_payment_is_not_candidate(): void
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
                'supplier-payment-reconciled-20i-60'
            ),
            'match_status' => 'reconciled',
        ]);

        $stats = app(
            SupplierPaymentCandidateService::class
        )->generate();

        $this->assertSame(0, $stats['considered']);
        $this->assertDatabaseMissing(
            'supplier_payment_allocations',
            [
                'bank_transaction_id' => $transaction->id,
                'accounting_bill_id' => $bill->id,
            ]
        );
    }
}
