<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Finance\DTOs\CashRiskAssessment;
use App\Domains\BusinessBrain\Finance\Services\CashManagementService;
use Tests\TestCase;

class CashManagementServiceTest extends TestCase
{
    public function test_cash_management_produces_recommendations(): void
    {
        $assessment = new CashRiskAssessment(
            cashPosition: 10000,
            outstandingInvoices: 20000,
            knownLiabilities: 15000,
            unknownExposure: 5000,
            confidence: 85
        );

        $result = app(
            CashManagementService::class
        )->assess(
            $assessment
        );

        $this->assertSame(
            100,
            $result['risk_score']
        );

        $this->assertCount(
            3,
            $result['recommendations']
        );
    }
}
