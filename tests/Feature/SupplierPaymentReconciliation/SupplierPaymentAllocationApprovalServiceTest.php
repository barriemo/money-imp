<?php

namespace Tests\Feature\SupplierPaymentReconciliation;

use App\Domains\Suppliers\Payments\Services\SupplierPaymentAllocationApprovalService;
use App\Models\AccountingBill;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Supplier;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPaymentAllocationApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_updates_bill_and_reconciles_payment(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create([
            'name' => '20i Limited',
        ]);

        $bill = AccountingBill::create([
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'currency' => 'GBP',
            'gross_amount' => 100,
            'paid_amount' => 0,
            'outstanding_amount' => 100,
        ]);

        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-31',
            'amount' => -100,
            'currency' => 'GBP',
            'description' => $supplier->name,
            'transaction_hash' => hash(
                'sha256',
                'supplier-payment-approval-100'
            ),
            'match_status' => 'unmatched',
        ]);

        $allocation = SupplierPaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_bill_id' => $bill->id,
            'amount' => 100,
            'status' => 'suggested',
            'confidence' => 100,
            'match_method' => 'exact_amount',
        ]);

        app(
            SupplierPaymentAllocationApprovalService::class
        )->approve(
            $allocation,
            $user->id
        );

        $bill->refresh();
        $transaction->refresh();
        $allocation->refresh();

        $this->assertSame(
            'approved',
            $allocation->status
        );

        $this->assertSame(
            100.0,
            (float) $bill->paid_amount
        );

        $this->assertSame(
            0.0,
            (float) $bill->outstanding_amount
        );

        $this->assertSame(
            'reconciled',
            $transaction->match_status
        );
    }

    public function test_partial_payment_updates_bill_and_transaction_correctly(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create([
            'name' => '20i Limited',
        ]);

        $bill = AccountingBill::create([
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'currency' => 'GBP',
            'gross_amount' => 100,
            'paid_amount' => 0,
            'outstanding_amount' => 100,
        ]);

        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-31',
            'amount' => -60,
            'currency' => 'GBP',
            'description' => $supplier->name,
            'transaction_hash' => hash(
                'sha256',
                'supplier-payment-partial-60'
            ),
            'match_status' => 'unmatched',
        ]);

        $allocation = SupplierPaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_bill_id' => $bill->id,
            'amount' => 60,
            'status' => 'suggested',
            'confidence' => 100,
        ]);

        app(
            SupplierPaymentAllocationApprovalService::class
        )->approve(
            $allocation,
            $user->id
        );

        $bill->refresh();
        $transaction->refresh();

        $this->assertSame(
            60.0,
            (float) $bill->paid_amount
        );

        $this->assertSame(
            40.0,
            (float) $bill->outstanding_amount
        );

        $this->assertSame(
            'reconciled',
            $transaction->match_status
        );
    }
}
