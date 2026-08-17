<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Finance\DTOs\CashRiskAssessment;
use Tests\TestCase;

class CashRiskAssessmentTest extends TestCase
{
    public function test_high_cash_exposure_produces_high_risk_score(): void
    {
        $assessment = new CashRiskAssessment(
            cashPosition: 10000,
            outstandingInvoices: 20000,
            knownLiabilities: 15000,
            unknownExposure: 5000,
            confidence: 85,
            evidence: [
                'source' => 'financial_truth',
            ]
        );

        $this->assertSame(
            100,
            $assessment->riskScore()
        );
    }

    public function test_safe_cash_position_produces_low_risk_score(): void
    {
        $assessment = new CashRiskAssessment(
            cashPosition: 50000,
            outstandingInvoices: 10000,
            knownLiabilities: 5000,
            unknownExposure: 0,
            confidence: 90,
        );

        $this->assertSame(
            0,
            $assessment->riskScore()
        );
    }
}
