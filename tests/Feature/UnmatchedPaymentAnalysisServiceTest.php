<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Analysis\UnmatchedPaymentAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnmatchedPaymentAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unmatched_payment_analysis_is_deterministic_with_no_payments(): void
    {
        $analysis =
            app(
                UnmatchedPaymentAnalysisService::class
            )->current();

        $this->assertSame(
            0,
            $analysis->paymentCount
        );

        $this->assertSame(
            0.0,
            $analysis->paymentValue
        );

        $this->assertSame(
            0,
            $analysis->uniqueExactMatchCount
        );

        $this->assertSame(
            0,
            $analysis->ambiguousExactMatchCount
        );

        $this->assertSame(
            0,
            $analysis->noExactMatchCount
        );
    }
}
