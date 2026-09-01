<?php

namespace Tests\Feature;

use App\Domains\Suppliers\Actions\AllocateSupplierTransaction;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\CostAllocation;
use App\Models\Project;
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
            'client',
            $allocation->allocation_type
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

    public function test_supplier_cost_can_be_allocated_to_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'amount' => -120,
            'currency' => 'GBP',
            'description' => 'PROJECT HOSTING',
            'transaction_hash' => hash(
                'sha256',
                'project-hosting-2026-08'
            ),
            'match_status' => 'unmatched',
        ]);

        app(
            AllocateSupplierTransaction::class
        )->execute(
            $transaction,
            'project',
            null,
            $user,
            $project->id,
        );

        $transaction->refresh();

        $this->assertSame(
            'project',
            $transaction->cost_purpose
        );

        $this->assertSame(
            'reviewed',
            $transaction->cost_review_status
        );

        $allocation = CostAllocation::query()
            ->where(
                'cost_allocatable_type',
                BankTransaction::class
            )
            ->where(
                'cost_allocatable_id',
                $transaction->id
            )
            ->first();

        $this->assertNotNull($allocation);

        $this->assertSame(
            $project->id,
            $allocation->project_id
        );

        $this->assertNull(
            $allocation->client_id
        );

        $this->assertSame(
            'project',
            $allocation->allocation_type
        );

        $this->assertSame(
            120.0,
            (float) $allocation->amount
        );
    }

    public function test_project_cost_without_project_remains_needs_review(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'amount' => -90,
            'currency' => 'GBP',
            'description' => 'UNKNOWN PROJECT COST',
            'transaction_hash' => hash(
                'sha256',
                'unknown-project-cost-2026-08'
            ),
            'match_status' => 'unmatched',
        ]);

        app(
            AllocateSupplierTransaction::class
        )->execute(
            $transaction,
            'project',
            null,
            $user,
        );

        $transaction->refresh();

        $this->assertSame(
            'project',
            $transaction->cost_purpose
        );

        $this->assertSame(
            'needs_review',
            $transaction->cost_review_status
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
