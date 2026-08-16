<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalPaymentEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_bank_evidence_becomes_one_payment_event(): void
    {
        $client = Client::factory()->create([
            'name' => 'MML Law',
        ]);

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
            ],
            [
                'source_type' => 'file_import',
                'description' => 'MML LAW',
            ],
        ] as $evidence) {
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'client_id' => $client->id,
                'transaction_date' => '2026-06-01',
                'amount' => 5160,
                'description' => $evidence['description'],
                'transaction_type' => 'customer_payment',
                'source_type' => $evidence['source_type'],
                'transaction_hash' => hash(
                    'sha256',
                    $evidence['source_type']
                ),
            ]);
        }

        $payments = app(
            CanonicalPaymentEvidenceService::class
        )->customerPayments();

        $this->assertCount(1, $payments);

        $this->assertSame(
            2,
            $payments->first()->evidenceCount
        );

        $this->assertSame(
            100,
            $payments->first()->confidence
        );
    }

    public function test_different_amounts_remain_separate_payment_events(): void
    {
        $client = Client::factory()->create();

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
            'currency' => 'GBP',
            'status' => 'active',
        ]);

        foreach ([100, 200] as $amount) {
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'client_id' => $client->id,
                'transaction_date' => '2026-06-01',
                'amount' => $amount,
                'description' => 'CLIENT PAYMENT',
                'transaction_type' => 'customer_payment',
                'source_type' => 'file_import',
                'transaction_hash' => hash(
                    'sha256',
                    (string) $amount
                ),
            ]);
        }

        $payments = app(
            CanonicalPaymentEvidenceService::class
        )->customerPayments();

        $this->assertCount(2, $payments);
    }

    public function test_different_clients_remain_separate_payment_events(): void
    {
        $clientOne = Client::factory()->create();
        $clientTwo = Client::factory()->create();

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
            'currency' => 'GBP',
            'status' => 'active',
        ]);

        foreach ([$clientOne, $clientTwo] as $client) {
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'client_id' => $client->id,
                'transaction_date' => '2026-06-01',
                'amount' => 500,
                'description' => 'CLIENT PAYMENT',
                'transaction_type' => 'customer_payment',
                'source_type' => 'file_import',
                'transaction_hash' => hash(
                    'sha256',
                    $client->id
                ),
            ]);
        }

        $payments = app(
            CanonicalPaymentEvidenceService::class
        )->customerPayments();

        $this->assertCount(2, $payments);
    }
}
