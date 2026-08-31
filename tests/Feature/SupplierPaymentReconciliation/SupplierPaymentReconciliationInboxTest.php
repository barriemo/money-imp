<?php

namespace Tests\Feature\SupplierPaymentReconciliation;

use App\Models\AccountingBill;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Supplier;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPaymentReconciliationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_payment_inbox_shows_suggested_allocations(): void
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
            'description' => '20i Limited',
            'transaction_hash' => hash(
                'sha256',
                'supplier-payment-inbox'
            ),
            'match_status' => 'unmatched',
        ]);

        SupplierPaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_bill_id' => $bill->id,
            'amount' => 100,
            'status' => 'suggested',
            'confidence' => 100,
            'match_method' => 'exact_amount',
            'reason' => 'Exact supplier payment match.',
        ]);

        $this->actingAs($user)
            ->get(route('supplier-payments.index'))
            ->assertOk()
            ->assertSee('20i Limited')
            ->assertSee('£100.00')
            ->assertSee('Exact supplier payment match.')
            ->assertSee('Approve')
            ->assertSee('Reject');
    }

    public function test_rejecting_supplier_payment_uses_approval_service(): void
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
            'description' => '20i Limited',
            'transaction_hash' => hash(
                'sha256',
                'supplier-payment-reject'
            ),
            'match_status' => 'unmatched',
        ]);

        $allocation = SupplierPaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_bill_id' => $bill->id,
            'amount' => 100,
            'status' => 'suggested',
            'confidence' => 100,
        ]);

        $this->actingAs($user)
            ->post(
                route('supplier-payments.reject', $allocation),
                ['reason' => 'Not the supplier bill I intended to pay.']
            )
            ->assertRedirect();

        $allocation->refresh();

        $this->assertSame('rejected', $allocation->status);
        $this->assertSame(
            'Not the supplier bill I intended to pay.',
            $allocation->metadata['rejection_reason']
        );

        $this->assertSame(
            0.0,
            (float) $bill->refresh()->paid_amount
        );
    }
}
