<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BankTruth\BankTransactionDeduplicationService;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankTransactionDeduplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_freeagent_and_file_import_versions_become_one_canonical_transaction(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'MML Law',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        $this->transaction(
            accountId: $account->id,
            clientId: $client->id,
            source: 'freeagent',
            description: 'MML LAW SOAMMLLAW///'
        );

        $this->transaction(
            accountId: $account->id,
            clientId: $client->id,
            source: 'file_import',
            description: 'MML LAW'
        );

        $truth =
            app(
                BankTransactionDeduplicationService::class
            )->current();

        $this->assertCount(
            1,
            $truth
        );

        $canonical =
            $truth->first();

        $this->assertSame(
            5160.0,
            $canonical->amount
        );

        $this->assertSame(
            $client->id,
            $canonical->clientId
        );

        $this->assertCount(
            2,
            $canonical->evidence
        );

        $this->assertSame(
            100,
            $canonical->confidence
        );

        /*
         * file_import is preferred as the primary
         * representation because it is closer to
         * raw bank evidence than FreeAgent.
         */
        $this->assertSame(
            'MML LAW',
            $canonical->description
        );
    }

    public function test_different_amounts_are_not_merged(): void
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

        $this->transaction(
            accountId: $account->id,
            clientId: $client->id,
            source: 'freeagent',
            description: 'CLIENT PAYMENT',
            amount: 1000
        );

        $this->transaction(
            accountId: $account->id,
            clientId: $client->id,
            source: 'file_import',
            description: 'CLIENT PAYMENT',
            amount: 1200
        );

        $truth =
            app(
                BankTransactionDeduplicationService::class
            )->current();

        $this->assertCount(
            2,
            $truth
        );
    }

    public function test_different_clients_are_not_merged(): void
    {
        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        $clientA =
            Client::factory()->create();

        $clientB =
            Client::factory()->create();

        $this->transaction(
            accountId: $account->id,
            clientId: $clientA->id,
            source: 'freeagent',
            description: 'CLIENT A'
        );

        $this->transaction(
            accountId: $account->id,
            clientId: $clientB->id,
            source: 'file_import',
            description: 'CLIENT B'
        );

        $truth =
            app(
                BankTransactionDeduplicationService::class
            )->current();

        $this->assertCount(
            2,
            $truth
        );
    }

    public function test_different_dates_are_not_merged(): void
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

        $this->transaction(
            accountId: $account->id,
            clientId: $client->id,
            source: 'freeagent',
            description: 'CLIENT PAYMENT',
            date: '2026-01-16'
        );

        $this->transaction(
            accountId: $account->id,
            clientId: $client->id,
            source: 'file_import',
            description: 'CLIENT PAYMENT',
            date: '2026-01-17'
        );

        $truth =
            app(
                BankTransactionDeduplicationService::class
            )->current();

        $this->assertCount(
            2,
            $truth
        );
    }

    public function test_legacy_unattributed_suggested_client_mapping_is_not_canonical(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Legacy Client Ltd',
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
            'transaction_date' => '2026-01-16',
            'amount' => 500,
            'description' => 'LEGACY CLIENT LTD',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'match_confidence' => 100,
            'matched_by' => null,
            'matched_at' => null,
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                'legacy-unattributed-suggestion'
            ),
            'metadata' => [
                'freeagent_unexplained_amount' => 500,
            ],
        ]);

        $truth =
            app(
                BankTransactionDeduplicationService::class
            )->current();

        /*
         * Strong payer/client evidence can exist while the
         * client attribution remains provisional.
         */
        $this->assertCount(
            0,
            $truth
        );
    }

    public function test_human_suggested_client_mapping_remains_canonical(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Human Confirmed Client Ltd',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'client_id' => $client->id,
                'transaction_date' => '2026-01-16',
                'amount' => 500,
                'description' => 'HUMAN CONFIRMED CLIENT LTD',
                'transaction_type' => 'customer_payment',
                'match_status' => 'suggested',
                'match_confidence' => 100,
                'matched_by' => $user->id,
                'matched_at' => now(),
                'source_type' => 'freeagent',
                'transaction_hash' => hash(
                    'sha256',
                    'human-attributed-suggestion'
                ),
                'metadata' => [
                    'freeagent_unexplained_amount' => 500,
                ],
            ]);

        $truth =
            app(
                BankTransactionDeduplicationService::class
            )->current();

        $this->assertCount(
            1,
            $truth
        );

        $canonical =
            $truth->sole();

        $this->assertSame(
            $transaction->id,
            $canonical->id
        );

        $this->assertSame(
            $client->id,
            $canonical->clientId
        );

        $this->assertSame(
            500.0,
            $canonical->amount
        );
    }

    private function transaction(
        string $accountId,
        string $clientId,
        string $source,
        string $description,
        float $amount = 5160,
        string $date = '2026-01-16'
    ): BankTransaction {
        return BankTransaction::create([
            'bank_account_id' => $accountId,

            'client_id' => $clientId,

            'transaction_date' => $date,

            'amount' => $amount,

            'description' => $description,

            'transaction_type' => 'customer_payment',

            'match_status' => 'reconciled',

            'match_confidence' => 100,

            'source_type' => $source,

            'transaction_hash' => hash(
                'sha256',
                implode(
                    '|',
                    [
                        $accountId,
                        $clientId,
                        $source,
                        $date,
                        $amount,
                        $description,
                    ]
                )
            ),
        ]);
    }
}
