<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\CreditTruth\CreditTruthService;
use App\Models\CreditFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTruthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_truth_distinguishes_reported_from_verified_exposure(): void
    {
        CreditFacility::create([
            'provider' => 'capital_on_tap',
            'name' => 'Capital on Tap',
            'facility_type' => 'business_credit_card',
            'currency' => 'GBP',
            'credit_limit' => 50000,
            'reported_balance' => 34351.65,
            'reported_balance_at' => now(),
            'minimum_payment' => 3435.16,
            'payment_due_at' => now()
                ->addDays(7)
                ->toDateString(),
            'verified' => false,
            'confidence' => 95,
            'status' => 'active',
        ]);

        $truth =
            app(
                CreditTruthService::class
            )->current();

        $this->assertSame(
            1,
            $truth->facilityCount
        );

        $this->assertSame(
            0,
            $truth->verifiedFacilityCount
        );

        $this->assertSame(
            34351.65,
            $truth->reportedExposure
        );

        $this->assertSame(
            0.0,
            $truth->verifiedExposure
        );

        $this->assertSame(
            15648.35,
            $truth->reportedAvailableCredit
        );

        $this->assertSame(
            3435.16,
            $truth->minimumPaymentsDue
        );

        $this->assertSame(
            0,
            $truth->confidence
        );
    }
}
