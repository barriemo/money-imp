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
}
