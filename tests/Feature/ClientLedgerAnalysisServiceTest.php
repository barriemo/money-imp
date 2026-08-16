<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Ledger\ClientLedgerAnalysisService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientLedgerAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_ledger_compares_cash_and_invoices_inside_evidence_window(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Ledger Client',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'PRE-HISTORY',
            'invoice_date' => '2025-12-01',
            'status' => 'paid',
            'gross_amount' => 1000,
            'paid_amount' => 1000,
            'outstanding_amount' => 0,
        ]);

        foreach (
            [
                ['2026-01-01', 'INV-001'],
                ['2026-02-01', 'INV-002'],
            ] as [$date, $number]
        ) {
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => $number,
                'invoice_date' => $date,
                'status' => 'paid',
                'gross_amount' => 500,
                'paid_amount' => 500,
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
                'amount' => 500,
                'description' => 'CUSTOMER PAYMENT',
                'transaction_type' => 'customer_payment',
                'source_type' => 'file_import',
                'transaction_hash' => hash(
                    'sha256',
                    'ledger-'.$index
                ),
            ]);
        }

        $position =
            app(
                ClientLedgerAnalysisService::class
            )->current()
                ->first();

        $this->assertSame(
            1000.0,
            $position->cashReceived
        );

        $this->assertSame(
            1000.0,
            $position->invoicedDuringPaymentWindow
        );

        $this->assertSame(
            0.0,
            $position->ledgerDifference
        );

        $this->assertTrue(
            $position->openingHistoryIncomplete
        );
    }
}
