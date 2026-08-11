<?php

namespace Tests\Feature;

use App\Domains\Suppliers\Actions\AllocateSupplierTransaction;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\CostAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTransactionReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_cost_can_be_allocated_to_client(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create();

        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'amount' => -60,
            'currency' => 'GBP',
            'description' => '20I LIMITED HOSTING',
            'transaction_hash' => hash(
                'sha256',
                '20i-2026-08'
            ),
            'match_status' => 'unmatched',
        ]);

        app(
            AllocateSupplierTransaction::class
        )->execute(
            $transaction,
            'client',
            $client->id,
            $user
        );

        $transaction->refresh();

        $this->assertSame(
            'client',
            $transaction->cost_purpose
        );

        $this->assertSame(
            'reviewed',
            $transaction->cost_review_status
        );

        $allocation =
            CostAllocation::query()
                ->where(
                    'cost_allocatable_type',
                    BankTransaction::class
                )
                ->where(
                    'cost_allocatable_id',
                    $transaction->id
                )
                ->first();

        $this->assertNotNull(
            $allocation
        );

        $this->assertSame(
            $client->id,
            $allocation->client_id
        );

        $this->assertSame(
            60.0,
            (float) $allocation->amount
        );
    }

    public function test_internal_cost_removes_client_allocation(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create();

        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'amount' => -50,
            'currency' => 'GBP',
            'description' => 'OPENAI',
            'transaction_hash' => hash(
                'sha256',
                'openai-2026-08'
            ),
            'match_status' => 'unmatched',
        ]);

        $action = app(
            AllocateSupplierTransaction::class
        );

        $action->execute(
            $transaction,
            'client',
            $client->id,
            $user
        );

        $action->execute(
            $transaction,
            'internal',
            null,
            $user
        );

        $transaction->refresh();

        $this->assertSame(
            'internal',
            $transaction->cost_purpose
        );

        $this->assertDatabaseMissing(
            'cost_allocations',
            [
                'cost_allocatable_type' => BankTransaction::class,

                'cost_allocatable_id' => $transaction->id,
            ]
        );
    }
}
