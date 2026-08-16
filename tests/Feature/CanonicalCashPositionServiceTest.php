<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\CashTruth\CanonicalCashPositionService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalCashPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_receipt_remains_cash_truth(): void
    {
        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
            'currency' => 'GBP',
            'status' => 'active',
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-06-01',
            'amount' => 300,
            'description' => 'UNKNOWN RECEIPT',
            'transaction_type' => 'customer_payment',
            'source_type' => 'rbs_pdf',
            'transaction_hash' => hash(
                'sha256',
                'unknown'
            ),
        ]);

        $position =
            app(
                CanonicalCashPositionService::class
            )->current();

        $this->assertSame(
            300.0,
            $position->totalIncomingCash
        );

        $this->assertSame(
            300.0,
            $position->unallocatedCash
        );
    }

    public function test_duplicate_bank_evidence_becomes_one_cash_movement(): void
    {
        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
            'currency' => 'GBP',
            'status' => 'active',
        ]);

        foreach ([
            [
                'source_type' => 'freeagent',
                'description' => 'MML LAW',
                'hash' => 'freeagent-mml',
            ],
            [
                'source_type' => 'file_import',
                'description' => 'MML LAW',
                'hash' => 'file-import-mml',
            ],
        ] as $evidence) {
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'transaction_date' => '2026-06-01',
                'amount' => 5160,
                'description' => $evidence['description'],
                'transaction_type' => 'customer_payment',
                'source_type' => $evidence['source_type'],
                'transaction_hash' => hash(
                    'sha256',
                    $evidence['hash']
                ),
            ]);
        }

        $position =
            app(
                CanonicalCashPositionService::class
            )->current();

        $this->assertSame(
            5160.0,
            $position->totalIncomingCash
        );

        $this->assertSame(
            1,
            $position->movementCount
        );
    }

    public function test_allocated_customer_payment_is_not_unallocated_cash(): void
    {
        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
            'currency' => 'GBP',
            'status' => 'active',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-06-01',
            'amount' => 1200,
            'description' => 'CUSTOMER PAYMENT',
            'transaction_type' => 'customer_payment',
            'source_type' => 'rbs_pdf',
            'transaction_hash' => hash(
                'sha256',
                'customer-payment'
            ),
        ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => null,
                'invoice_number' => 'INV-CASH-001',
                'status' => 'paid',
                'gross_amount' => 1200,
                'paid_amount' => 1200,
                'outstanding_amount' => 0,
            ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_invoice_id' => $invoice->id,
            'amount' => 1200,
            'status' => 'approved',
            'confidence' => 100,
            'match_method' => 'manual',
        ]);

        $position =
            app(
                CanonicalCashPositionService::class
            )->current();

        $this->assertSame(
            1200.0,
            $position->totalIncomingCash
        );

        $this->assertSame(
            0.0,
            $position->unallocatedCash
        );
    }
}
