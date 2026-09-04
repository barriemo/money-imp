<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessTruthCoverageService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTruthCoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_truth_coverage_reports_known_and_missing_sources(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Walker',
                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-001',

            'status' => 'paid',

            'invoice_date' => now(),

            'due_date' => now(),

            'currency' => 'GBP',

            'net_amount' => 1000,

            'tax_amount' => 200,

            'gross_amount' => 1200,

            'paid_amount' => 1200,

            'outstanding_amount' => 0,
        ]);

        PaymentIdentity::create([
            'client_id' => $client->id,

            'identity_type' => 'reference',

            'identity_value' => 'WALKER-001',

            'normalized_value' => 'walker-001',

            'direction' => 'incoming',

            'confidence' => 95,
        ]);

        $coverage =
            app(
                BusinessTruthCoverageService::class
            )->forClient(
                $client
            );

        $this->assertTrue(
            $coverage->hasInvoices
        );

        $this->assertTrue(
            $coverage->hasPaymentIdentity
        );

        $this->assertFalse(
            $coverage->hasWorkLogs
        );

        $this->assertFalse(
            $coverage->hasServices
        );

        $this->assertSame(
            'Walker',
            $coverage->client
        );
    }

    public function test_unattributed_suggested_client_mapping_is_not_bank_coverage(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Provisional Coverage Client',
                'status' => 'active',
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
            'transaction_date' => '2026-09-01',
            'amount' => 1200,
            'description' => 'PROVISIONAL CLIENT PAYMENT',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'match_confidence' => 100,
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                'provisional-bank-coverage'
            ),
        ]);

        $coverage =
            app(
                BusinessTruthCoverageService::class
            )->forClient(
                $client
            );

        $this->assertSame(
            0,
            $coverage->bankTransactionCount
        );

        $this->assertFalse(
            $coverage->hasBankTransactions
        );

        $this->assertSame(
            0,
            $coverage->confidence
        );
    }

    public function test_automated_suggested_client_mapping_is_not_bank_coverage(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Automated Coverage Client',
                'status' => 'active',
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
            'transaction_date' => '2026-09-01',
            'amount' => 900,
            'description' => 'AUTOMATED CLIENT PAYMENT',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'match_confidence' => 100,
            'source_type' => 'freeagent',
            'metadata' => [
                'reconciliation_provenance' =>
                    'automated_candidate',
            ],
            'transaction_hash' => hash(
                'sha256',
                'automated-bank-coverage'
            ),
        ]);

        $coverage =
            app(
                BusinessTruthCoverageService::class
            )->forClient(
                $client
            );

        $this->assertSame(
            0,
            $coverage->bankTransactionCount
        );

        $this->assertFalse(
            $coverage->hasBankTransactions
        );
    }

    public function test_human_suggested_client_mapping_is_bank_coverage(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Human Coverage Client',
                'status' => 'active',
            ]);

        $user =
            \App\Models\User::factory()->create();

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
            'transaction_date' => '2026-09-01',
            'amount' => 750,
            'description' => 'HUMAN ATTRIBUTED PAYMENT',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'match_confidence' => 100,
            'matched_by' => $user->id,
            'matched_at' => now(),
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                'human-bank-coverage'
            ),
        ]);

        $coverage =
            app(
                BusinessTruthCoverageService::class
            )->forClient(
                $client
            );

        $this->assertSame(
            1,
            $coverage->bankTransactionCount
        );

        $this->assertTrue(
            $coverage->hasBankTransactions
        );

        $this->assertSame(
            17,
            $coverage->confidence
        );
    }

    public function test_reconciled_client_mapping_is_bank_coverage(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Reconciled Coverage Client',
                'status' => 'active',
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
            'transaction_date' => '2026-09-01',
            'amount' => 600,
            'description' => 'RECONCILED CLIENT PAYMENT',
            'transaction_type' => 'customer_payment',
            'match_status' => 'reconciled',
            'match_confidence' => 100,
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                'reconciled-bank-coverage'
            ),
        ]);

        $coverage =
            app(
                BusinessTruthCoverageService::class
            )->forClient(
                $client
            );

        $this->assertSame(
            1,
            $coverage->bankTransactionCount
        );

        $this->assertTrue(
            $coverage->hasBankTransactions
        );
    }

}
