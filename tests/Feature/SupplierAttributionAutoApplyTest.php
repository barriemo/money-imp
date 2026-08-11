<?php

namespace Tests\Feature;

use App\Domains\Suppliers\Rules\SupplierAttributionAutoApplier;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\CostAllocation;
use App\Models\SupplierAttributionRule;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAttributionAutoApplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_rule_auto_attributes_new_cost(): void
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

        SupplierAttributionRule::create([
            'supplier_profile_id' => $supplier->id,

            'match_type' => 'contains',

            'match_value' => 'eukhost vps 12345',

            'purpose' => 'client',

            'client_id' => $client->id,

            'confidence' => 100,

            'apply_historically' => true,

            'active' => true,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,

            'transaction_date' => '2026-08-11',

            'amount' => -50,

            'currency' => 'GBP',

            'description' => 'EUKHOST VPS 12345',

            'transaction_hash' => hash(
                'sha256',
                'new-eukhost-cost'
            ),

            'match_status' => 'unmatched',
        ]);

        $applied = app(
            SupplierAttributionAutoApplier::class
        )->apply(
            $transaction,
            $user
        );

        $this->assertTrue($applied);

        $transaction->refresh();

        $this->assertSame(
            'client',
            $transaction->cost_purpose
        );

        $this->assertSame(
            'reviewed',
            $transaction->cost_review_status
        );

        $this->assertDatabaseHas(
            'cost_allocations',
            [
                'cost_allocatable_id' => $transaction->id,

                'client_id' => $client->id,
            ]
        );

        $this->assertSame(
            1,
            CostAllocation::query()->count()
        );
    }
}
