<?php

namespace Tests\Feature;

use App\Domains\RevenueTruth\RevenueRecommendationEngine;
use App\Models\Client;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_supplier_recovery_creates_revenue_recommendation(): void
    {
        $client =
            Client::factory()->create();

        $supplier =
            SupplierProfile::create([
                'supplier_name' => 'Domain Supplier',

                'supplier_key' => 'domain-supplier',

                'category' => 'hosting',

                'recoverable' => true,

                'active' => true,
            ]);

        $asset =
            SupplierAsset::create([
                'supplier_profile_id' => $supplier->id,

                'client_id' => $client->id,

                'asset_type' => 'domain',

                'asset_key' => 'example-com',

                'name' => 'example.com',

                'purpose' => 'client',

                'billable' => true,

                'active' => true,

                'observed_cost' => 40,

                'confidence' => 100,
            ]);

        $recommendation = app(
            RevenueRecommendationEngine::class
        )->recommend(
            $asset
        );

        $this->assertNotNull(
            $recommendation
        );

        $this->assertSame(
            'missing_recovery',
            $recommendation->type
        );

        $this->assertSame(
            '40.00',
            $recommendation
                ->estimated_monthly_value
        );

        $this->assertSame(
            '480.00',
            $recommendation
                ->estimated_annual_value
        );

        $this->assertSame(
            95,
            $recommendation->confidence
        );

        $this->assertCount(
            1,
            $recommendation->evidence
        );
    }
}
