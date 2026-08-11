<?php

namespace Tests\Feature;

use App\Domains\Suppliers\Rules\SupplierAttributionRuleApplier;
use App\Domains\Suppliers\Rules\SupplierAttributionRuleLearner;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\CostAllocation;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAttributionLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_learned_rule_can_apply_to_matching_history(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create();

        $account = BankAccount::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => 'EUKhost',
            'supplier_key' => 'eukhost',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        $first = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-06-01',
            'amount' => -50,
            'currency' => 'GBP',
            'description' => 'EUKHOST VPS 12345',
            'transaction_hash' => hash(
                'sha256',
                'eukhost-june'
            ),
            'match_status' => 'unmatched',
        ]);

        $second = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-07-01',
            'amount' => -50,
            'currency' => 'GBP',
            'description' => 'EUKHOST VPS 12345',
            'transaction_hash' => hash(
                'sha256',
                'eukhost-july'
            ),
            'match_status' => 'unmatched',
        ]);

        $rule = app(
            SupplierAttributionRuleLearner::class
        )->learn(
            $supplier,
            $first,
            'client',
            $client->id,
            true
        );

        $applied = app(
            SupplierAttributionRuleApplier::class
        )->apply(
            $rule,
            $user
        );

        $this->assertSame(
            2,
            $applied
        );

        $this->assertDatabaseHas(
            'cost_allocations',
            [
                'cost_allocatable_id' => $first->id,

                'client_id' => $client->id,
            ]
        );

        $this->assertDatabaseHas(
            'cost_allocations',
            [
                'cost_allocatable_id' => $second->id,

                'client_id' => $client->id,
            ]
        );

        $this->assertSame(
            2,
            CostAllocation::query()->count()
        );
    }
}
