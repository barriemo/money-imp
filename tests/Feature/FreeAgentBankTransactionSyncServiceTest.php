<?php

namespace Tests\Feature;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentBankTransactionSyncService;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\InvestigationEvidenceBus;
use App\Domains\Suppliers\Payments\Services\SupplierPaymentAllocationApprovalService;
use App\Models\AccountingBill;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use App\Models\Supplier;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FreeAgentBankTransactionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_freeagent_transaction_starts_unmatched(): void
    {
        $connection = $this->connection();
        $account = $this->account($connection);

        $this->fakeFreeAgent([
            $this->transactionPayload(),
        ]);

        $this->mockEvidenceBus();

        $run = app(FreeAgentBankTransactionSyncService::class)
            ->sync($connection);

        $this->assertSame(1, $run->records_created);

        $transaction = BankTransaction::firstOrFail();

        $this->assertSame('unmatched', $transaction->match_status);
        $this->assertSame('freeagent', $transaction->source_type);
        $this->assertSame(
            '12.50',
            (string) $transaction->metadata['freeagent_unexplained_amount']
        );
    }

    public function test_freeagent_sync_does_not_overwrite_reconciled_status(): void
    {
        $connection = $this->connection();
        $account = $this->account($connection);

        $transaction = $this->existingTransaction(
            $account,
            'reconciled'
        );

        $this->fakeFreeAgent([
            $this->transactionPayload(),
        ]);

        $this->mockEvidenceBus();

        app(FreeAgentBankTransactionSyncService::class)
            ->sync($connection);

        $transaction->refresh();

        $this->assertSame('reconciled', $transaction->match_status);
        $this->assertSame(
            '12.50',
            (string) $transaction->metadata['freeagent_unexplained_amount']
        );
    }

    public function test_freeagent_sync_does_not_overwrite_partially_allocated_status(): void
    {
        $connection = $this->connection();
        $account = $this->account($connection);

        $transaction = $this->existingTransaction(
            $account,
            'partially_allocated'
        );

        $this->fakeFreeAgent([
            $this->transactionPayload(),
        ]);

        $this->mockEvidenceBus();

        app(FreeAgentBankTransactionSyncService::class)
            ->sync($connection);

        $transaction->refresh();

        $this->assertSame(
            'partially_allocated',
            $transaction->match_status
        );
    }

    public function test_freeagent_sync_does_not_overwrite_suggested_status(): void
    {
        $connection = $this->connection();
        $account = $this->account($connection);

        $transaction = $this->existingTransaction(
            $account,
            'suggested'
        );

        $this->fakeFreeAgent([
            $this->transactionPayload(),
        ]);

        $this->mockEvidenceBus();

        app(FreeAgentBankTransactionSyncService::class)
            ->sync($connection);

        $transaction->refresh();

        $this->assertSame('suggested', $transaction->match_status);
    }

    public function test_approved_supplier_allocation_survives_freeagent_resync(): void
    {
        $connection = $this->connection();

        $account = BankAccount::factory()->create([
            'name' => 'FreeAgent Current Account',
            'currency' => 'GBP',
        ]);

        ExternalRecord::create([
            'external_connection_id' => $connection->id,
            'resource_type' => 'bank_account',
            'external_id' => '123',
            'recordable_type' => BankAccount::class,
            'recordable_id' => $account->id,
            'external_reference' => 'https://api.freeagent.com/v2/bank_accounts/123',
        ]);

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

        $transactionPayload = [
            'url' => 'https://api.freeagent.com/v2/bank_transactions/987',
            'transaction_id' => '987',
            'dated_on' => '2026-08-31',
            'amount' => -100,
            'description' => '20i Limited',
            'unexplained_amount' => 100,
            'is_manual' => false,
            'full_description' => '20i Limited',
            'uploaded_at' => '2026-08-31T10:00:00Z',
            'matching_transactions_count' => 0,
            'bank_transaction_explanations' => [],
        ];

        Http::fake([
            '*' => Http::response([
                'bank_transactions' => [
                    $transactionPayload,
                ],
            ], 200),
        ]);

        $bus = Mockery::mock(
            InvestigationEvidenceBus::class
        );

        $bus
            ->shouldReceive('publish')
            ->twice()
            ->andReturn(collect());

        $this->app->instance(
            InvestigationEvidenceBus::class,
            $bus
        );

        $service = app(
            FreeAgentBankTransactionSyncService::class
        );

        $firstRun = $service->sync($connection);

        $this->assertSame(1, $firstRun->records_created);

        $transaction = BankTransaction::query()->firstOrFail();

        $this->assertSame(
            'unmatched',
            $transaction->match_status
        );

        $allocation = SupplierPaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_bill_id' => $bill->id,
            'amount' => 100,
            'status' => 'suggested',
            'confidence' => 100,
            'match_method' => 'exact_amount',
        ]);

        $user = User::factory()->create();

        app(
            SupplierPaymentAllocationApprovalService::class
        )->approve(
            $allocation,
            $user->id
        );

        $transaction->refresh();
        $bill->refresh();
        $allocation->refresh();

        $this->assertSame(
            'approved',
            $allocation->status
        );

        $this->assertSame(
            'reconciled',
            $transaction->match_status
        );

        $this->assertSame(
            100.0,
            (float) $bill->paid_amount
        );

        $this->assertSame(
            0.0,
            (float) $bill->outstanding_amount
        );

        $secondRun = $service->sync($connection->refresh());

        $this->assertSame(0, $secondRun->records_created);
        $this->assertSame(1, $secondRun->records_updated);

        $transaction->refresh();
        $bill->refresh();
        $allocation->refresh();

        $this->assertSame(
            'reconciled',
            $transaction->match_status
        );

        $this->assertSame(
            'approved',
            $allocation->status
        );

        $this->assertSame(
            100.0,
            (float) $allocation->amount
        );

        $this->assertSame(
            100.0,
            (float) $bill->paid_amount
        );

        $this->assertSame(
            0.0,
            (float) $bill->outstanding_amount
        );

        $this->assertDatabaseCount(
            'supplier_payment_allocations',
            1
        );
    }

    private function connection(): ExternalConnection
    {
        return ExternalConnection::create([
            'provider' => 'freeagent',
            'name' => 'Purple Imp FreeAgent',
            'status' => 'connected',
            'access_token' => 'test-access',
            'refresh_token' => 'test-refresh',
            'token_expires_at' => now()->addHour(),
        ]);
    }

    private function account(
        ExternalConnection $connection
    ): BankAccount {
        $account = BankAccount::factory()->create([
            'name' => 'Business Current Account',
            'currency' => 'GBP',
        ]);

        ExternalRecord::create([
            'external_connection_id' => $connection->id,
            'resource_type' => 'bank_account',
            'external_id' => 'bank-account-1',
            'recordable_type' => BankAccount::class,
            'recordable_id' => $account->id,
            'external_reference' => 'https://api.freeagent.com/v2/bank_accounts/1',
        ]);

        return $account;
    }

    private function existingTransaction(
        BankAccount $account,
        string $status
    ): BankTransaction {
        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-31',
            'amount' => -100.00,
            'currency' => 'GBP',
            'description' => 'ACME SUPPLIER',
            'reference' => 'fa-transaction-1',
            'transaction_type' => 'imported',
            'match_status' => $status,
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                implode('|', [
                    $account->id,
                    'fa-transaction-1',
                    '2026-08-31',
                    '-100.00',
                    'ACME SUPPLIER',
                ])
            ),
        ]);

        return $transaction;
    }

    private function transactionPayload(): array
    {
        return [
            'url' => 'https://api.freeagent.com/v2/bank_transactions/fa-transaction-1',
            'transaction_id' => 'fa-transaction-1',
            'dated_on' => '2026-08-31',
            'amount' => '-100.00',
            'description' => 'ACME SUPPLIER',
            'unexplained_amount' => '12.50',
            'full_description' => 'ACME SUPPLIER PAYMENT',
            'uploaded_at' => '2026-08-31T09:00:00Z',
            'is_manual' => false,
            'matching_transactions_count' => 0,
            'created_at' => '2026-08-31T09:00:00Z',
            'updated_at' => '2026-08-31T09:00:00Z',
            'bank_transaction_explanations' => [],
        ];
    }

    private function fakeFreeAgent(array $transactions): void
    {
        Http::fake([
            '*' => Http::response([
                'bank_transactions' => $transactions,
            ], 200),
        ]);
    }

    private function mockEvidenceBus(): void
    {
        $bus = Mockery::mock(InvestigationEvidenceBus::class);

        $bus
            ->shouldReceive('publish')
            ->once()
            ->andReturn(collect());

        $this->app->instance(
            InvestigationEvidenceBus::class,
            $bus
        );
    }
}
