<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence\ClientLedgerRiskService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientLedgerRiskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_history_anomaly_can_out_rank_larger_incomplete_history_variance(): void
    {
        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        $incomplete =
            Client::factory()->create([
                'name' => 'Incomplete History Client',
            ]);

        AccountingInvoice::create([
            'client_id' => $incomplete->id,
            'invoice_number' => 'OLD-001',
            'invoice_date' => '2025-12-01',
            'status' => 'paid',
            'gross_amount' => 1000,
            'paid_amount' => 1000,
            'outstanding_amount' => 0,
        ]);

        AccountingInvoice::create([
            'client_id' => $incomplete->id,
            'invoice_number' => 'INC-001',
            'invoice_date' => '2026-01-01',
            'status' => 'paid',
            'gross_amount' => 20000,
            'paid_amount' => 20000,
            'outstanding_amount' => 0,
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $incomplete->id,
            'transaction_date' => '2026-01-10',
            'amount' => 1000,
            'description' => 'INCOMPLETE HISTORY CLIENT',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'incomplete-history'
            ),
        ]);

        $complete =
            Client::factory()->create([
                'name' => 'Complete History Client',
            ]);

        AccountingInvoice::create([
            'client_id' => $complete->id,
            'invoice_number' => 'COMP-001',
            'invoice_date' => '2026-01-01',
            'status' => 'paid',
            'gross_amount' => 15000,
            'paid_amount' => 15000,
            'outstanding_amount' => 0,
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $complete->id,
            'transaction_date' => '2026-01-10',
            'amount' => 1000,
            'description' => 'COMPLETE HISTORY CLIENT',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'complete-history'
            ),
        ]);

        $risks =
            app(
                ClientLedgerRiskService::class
            )->current();

        $completeRisk =
            $risks->firstWhere(
                'clientId',
                $complete->id
            );

        $incompleteRisk =
            $risks->firstWhere(
                'clientId',
                $incomplete->id
            );

        $this->assertSame(
            'high_confidence_anomaly',
            $completeRisk->classification
        );

        $this->assertSame(
            'historical_evidence_incomplete',
            $incompleteRisk->classification
        );

        $this->assertGreaterThan(
            $incompleteRisk->priority,
            $completeRisk->priority
        );

        $this->assertGreaterThan(
            $incompleteRisk->confidence,
            $completeRisk->confidence
        );
    }

    public function test_invoice_only_outstanding_client_is_explicit_ledger_risk(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'VF Electrical Services Ltd',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'VF-001',
            'invoice_date' => '2026-06-30',
            'due_date' => '2026-07-07',
            'status' => 'overdue',
            'gross_amount' => 7218,
            'paid_amount' => 0,
            'outstanding_amount' => 7218,
        ]);

        $risk =
            app(
                ClientLedgerRiskService::class
            )
                ->current()
                ->firstWhere(
                    'clientId',
                    $client->id
                );

        $this->assertNotNull(
            $risk
        );

        $this->assertSame(
            'invoice_balance_without_canonical_payment_evidence',
            $risk->classification
        );

        $this->assertSame(
            0.0,
            $risk->cashReceived
        );

        $this->assertSame(
            7218.0,
            $risk->invoiceValue
        );

        $this->assertSame(
            -7218.0,
            $risk->difference
        );

        $this->assertSame(
            80,
            $risk->confidence
        );

        $this->assertTrue(
            collect(
                $risk->reasons
            )->contains(
                fn (string $reason) => str_contains(
                    $reason,
                    'does not prove'
                )
            )
        );
    }

    public function test_invoice_only_priority_uses_live_outstanding_while_preserving_raw_ledger_evidence(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Part Paid Debtor',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'PART-001',
            'invoice_date' => '2026-06-30',
            'due_date' => '2026-07-07',
            'status' => 'overdue',
            'gross_amount' => 15000,
            'paid_amount' => 7800,
            'outstanding_amount' => 7200,
        ]);

        $risk =
            app(
                ClientLedgerRiskService::class
            )
                ->current()
                ->firstWhere(
                    'clientId',
                    $client->id
                );

        $this->assertNotNull(
            $risk
        );

        $this->assertSame(
            'invoice_balance_without_canonical_payment_evidence',
            $risk->classification
        );

        /*
         * Preserve the raw evidence facts.
         */
        $this->assertSame(
            15000.0,
            $risk->invoiceValue
        );

        $this->assertSame(
            -15000.0,
            $risk->difference
        );

        /*
         * But executive priority is based on the live
         * accounting-reported outstanding balance:
         *
         * £7,200 / £500 = 14 value points
         * + 40 evidence points
         * = priority 54.
         */
        $this->assertSame(
            54,
            $risk->priority
        );

        $this->assertTrue(
            collect(
                $risk->reasons
            )->contains(
                fn (string $reason) => str_contains(
                    $reason,
                    '£7,200.00 outstanding'
                )
            )
        );

        $this->assertTrue(
            collect(
                $risk->reasons
            )->contains(
                fn (string $reason) => str_contains(
                    $reason,
                    'Executive priority is based'
                )
            )
        );
    }

    public function test_accounting_paid_without_bank_evidence_is_not_called_reconciled(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Accounting Paid Client',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'PAID-001',
            'invoice_date' => '2026-06-30',
            'due_date' => '2026-07-07',
            'status' => 'paid',
            'gross_amount' => 1200,
            'paid_amount' => 1200,
            'outstanding_amount' => 0,
        ]);

        $risk =
            app(
                ClientLedgerRiskService::class
            )
                ->current()
                ->firstWhere(
                    'clientId',
                    $client->id
                );

        $this->assertNotNull(
            $risk
        );

        $this->assertSame(
            'accounting_paid_without_canonical_payment_evidence',
            $risk->classification
        );

        $this->assertSame(
            65,
            $risk->confidence
        );
    }
}
