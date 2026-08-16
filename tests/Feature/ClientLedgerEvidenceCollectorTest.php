<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Hypothesis;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientLedgerEvidenceCollector;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientLedgerEvidenceCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_client_ledger_produces_support_and_missing_evidence(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Peak Renewables',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2024-03-02',
            'amount' => 90,
            'description' => 'PEAK RENEWABLES',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'peak-bank'
            ),
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1686',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 21990,
            'paid_amount' => 21990,
            'outstanding_amount' => 0,
        ]);

        $hypothesis =
            new Hypothesis(
                statement: 'Those large invoices were paid into our old HSBC account.',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $evidence =
            app(
                ClientLedgerEvidenceCollector::class
            )->collect(
                $hypothesis
            );

        $positions =
            collect(
                $evidence
            )
                ->pluck(
                    'position'
                )
                ->all();

        $this->assertContains(
            'supports',
            $positions
        );

        $this->assertContains(
            'missing',
            $positions
        );
    }
}
