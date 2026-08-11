<?php

namespace Tests\Feature;

use App\Domains\Suppliers\Assets\SupplierAssetClassifier;
use App\Domains\Suppliers\Assets\SupplierAssetDetector;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAssetDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_can_be_detected_from_transaction(): void
    {
        $account = BankAccount::factory()->create();

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'amount' => -18,
            'currency' => 'GBP',
            'description' => 'NAME.COM renewal example.co.uk',
            'transaction_hash' => hash(
                'sha256',
                'name-example-domain'
            ),
            'match_status' => 'unmatched',
        ]);

        $assets = app(
            SupplierAssetDetector::class
        )->detect($transaction);

        $this->assertTrue(
            $assets->contains(
                fn (array $asset) => $asset['type'] === 'domain'
                    && $asset['key']
                        === 'example.co.uk'
            )
        );
    }

    public function test_detected_asset_can_be_persisted(): void
    {
        $account = BankAccount::factory()->create();

        $supplier = SupplierProfile::create([
            'supplier_name' => 'Name.com',
            'supplier_key' => 'name com',
            'category' => 'domains',
            'recoverable' => true,
            'active' => true,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'amount' => -18,
            'currency' => 'GBP',
            'description' => 'NAME.COM renewal example.co.uk',
            'transaction_hash' => hash(
                'sha256',
                'name-example-persist'
            ),
            'match_status' => 'unmatched',
        ]);

        $count = app(
            SupplierAssetClassifier::class
        )->classify(
            $supplier,
            $transaction
        );

        $this->assertSame(1, $count);

        $this->assertDatabaseHas(
            'supplier_assets',
            [
                'supplier_profile_id' => $supplier->id,

                'asset_type' => 'domain',

                'asset_key' => 'example.co.uk',
            ]
        );

        $asset = SupplierAsset::first();

        $this->assertSame(
            18.0,
            (float) $asset->observed_cost
        );
    }
}
