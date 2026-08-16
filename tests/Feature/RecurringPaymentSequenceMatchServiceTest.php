<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Reconciliation\RecurringPaymentSequenceMatchService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringPaymentSequenceMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_payment_sequence_is_matched_chronologically(): void
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

        foreach (
            [
                '2026-01-01',
                '2026-02-01',
                '2026-03-01',
            ] as $index => $date
        ) {
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => sprintf(
                    'INV-REC-%03d',
                    $index + 1
                ),

                'invoice_date' => $date,

                'status' => 'paid',

                'gross_amount' => 180,

                'paid_amount' => 180,

                'outstanding_amount' => 0,
            ]);
        }

        foreach (
            [
                '2026-01-10',
                '2026-02-10',
                '2026-03-10',
            ] as $index => $date
        ) {
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => $date,

                'amount' => 180,

                'description' => 'RECURRING CUSTOMER PAYMENT',

                'transaction_type' => 'customer_payment',

                'source_type' => 'file_import',

                'transaction_hash' => hash(
                    'sha256',
                    'recurring-'.$index
                ),
            ]);
        }

        $stats =
            app(
                RecurringPaymentSequenceMatchService::class
            )->generate();

        $this->assertSame(
            1,
            $stats['groups_matched']
        );

        $this->assertSame(
            3,
            $stats['payments_matched']
        );

        $this->assertSame(
            540.0,
            $stats['value']
        );

        $this->assertSame(
            3,
            PaymentAllocation::query()
                ->where(
                    'match_method',
                    'canonical_recurring_sequence'
                )
                ->count()
        );
    }

    public function test_sequence_is_not_matched_when_payment_and_invoice_counts_differ(): void
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

        foreach (
            [
                '2026-01-01',
                '2026-02-01',
                '2026-03-01',
            ] as $index => $date
        ) {
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => sprintf(
                    'INV-MISMATCH-%03d',
                    $index + 1
                ),

                'invoice_date' => $date,

                'status' => 'paid',

                'gross_amount' => 90,

                'paid_amount' => 90,

                'outstanding_amount' => 0,
            ]);
        }

        foreach (
            [
                '2026-01-10',
                '2026-02-10',
            ] as $index => $date
        ) {
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => $date,

                'amount' => 90,

                'description' => 'RECURRING CUSTOMER PAYMENT',

                'transaction_type' => 'customer_payment',

                'source_type' => 'file_import',

                'transaction_hash' => hash(
                    'sha256',
                    'mismatch-'.$index
                ),
            ]);
        }

        $stats =
            app(
                RecurringPaymentSequenceMatchService::class
            )->generate();

        $this->assertSame(
            0,
            $stats['groups_matched']
        );

        $this->assertSame(
            0,
            $stats['payments_matched']
        );

        $this->assertSame(
            0,
            PaymentAllocation::count()
        );
    }

    public function test_preview_reports_sequence_matches_without_writing_allocations(): void
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

        foreach (
            [
                '2026-01-01',
                '2026-02-01',
            ] as $index => $date
        ) {
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => sprintf(
                    'INV-PREVIEW-%03d',
                    $index + 1
                ),

                'invoice_date' => $date,

                'status' => 'paid',

                'gross_amount' => 180,

                'paid_amount' => 180,

                'outstanding_amount' => 0,
            ]);
        }

        foreach (
            [
                '2026-01-10',
                '2026-02-10',
            ] as $index => $date
        ) {
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => $date,

                'amount' => 180,

                'description' => 'PREVIEW CUSTOMER PAYMENT',

                'transaction_type' => 'customer_payment',

                'source_type' => 'file_import',

                'transaction_hash' => hash(
                    'sha256',
                    'preview-'.$index
                ),
            ]);
        }

        $stats =
            app(
                RecurringPaymentSequenceMatchService::class
            )->preview();

        $this->assertSame(
            1,
            $stats['groups_matched']
        );

        $this->assertSame(
            2,
            $stats['payments_matched']
        );

        $this->assertSame(
            360.0,
            $stats['value']
        );

        $this->assertSame(
            0,
            PaymentAllocation::count()
        );
    }
}
