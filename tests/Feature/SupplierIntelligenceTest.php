<?php

namespace Tests\Feature;

use App\Domains\Suppliers\Services\SupplierAnalysisService;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_supplier_spend_is_analysed(): void
    {
        $account = BankAccount::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => '20i',
            'supplier_key' => '20i',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        foreach ([
            ['2026-06-01', -60],
            ['2026-07-01', -60],
            ['2026-08-01', -60],
        ] as [$date, $amount]) {
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'transaction_date' => $date,

                'amount' => $amount,

                'currency' => 'GBP',

                'description' => '20I LIMITED HOSTING',
                'transaction_hash' => hash(
                    'sha256',
                    $date.$amount
                ),

                'match_status' => 'unmatched',
            ]);
        }

        $analysis = app(
            SupplierAnalysisService::class
        )->analyse($supplier);

        $this->assertSame(
            3,
            $analysis->transactionCount
        );

        $this->assertSame(
            180.0,
            $analysis->totalSpend
        );

        $this->assertSame(
            60.0,
            $analysis->averageMonthlySpend
        );

        $this->assertSame(
            720.0,
            $analysis->annualisedSpend
        );

        $this->assertSame(
            180.0,
            $analysis->unknownSpend
        );

        $this->assertTrue(
            $analysis->recurring
        );
    }

    public function test_reviewed_costs_reduce_unknown_spend(): void
    {
        $account = BankAccount::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => 'EUKhost',
            'supplier_key' => 'eukhost',
            'category' => 'hosting',
            'recoverable' => true,
            'active' => true,
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'amount' => -100,
            'currency' => 'GBP',
            'description' => 'EUKHOST LIMITED',
            'transaction_hash' => hash(
                'sha256',
                'eukhost-known'
            ),
            'match_status' => 'unmatched',
            'cost_purpose' => 'internal',
            'cost_review_status' => 'reviewed',
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-02',
            'amount' => -50,
            'currency' => 'GBP',
            'description' => 'EUKHOST LIMITED',
            'transaction_hash' => hash(
                'sha256',
                'eukhost-unknown'
            ),
            'match_status' => 'unmatched',
        ]);

        $analysis = app(
            SupplierAnalysisService::class
        )->analyse($supplier);

        $this->assertSame(
            150.0,
            $analysis->totalSpend
        );

        $this->assertSame(
            100.0,
            $analysis->internalSpend
        );

        $this->assertSame(
            50.0,
            $analysis->unknownSpend
        );

        $this->assertSame(
            50.0,
            $analysis->potentialRecovery
        );
    }
}
