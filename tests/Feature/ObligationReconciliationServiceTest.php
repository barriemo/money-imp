<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\ObligationTruth\LiabilityAssessment;
use App\Domains\BusinessBrain\ObligationTruth\ObligationReconciliationService;
use App\Domains\BusinessBrain\ObligationTruth\StatutorySettlementEvidence;
use Tests\TestCase;

class ObligationReconciliationServiceTest extends TestCase
{
    public function test_reconciliation_separates_reported_exposure_from_observed_payments(): void
    {
        $assessment = new LiabilityAssessment(
            reportedTotal: 4523.30,
            currentReportedExposure: 4523.30,
            reportedOverdue: 4523.30,
            reportedUpcoming: 0,
            historicalReportedUnresolved: 0,
            settlementUnresolved: 4523.30,
            bankTransactionEvidenceCurrent: true,
            canInferPaymentAbsence: false,
            unknownCategories: [],
            currentItems: [],
            settlementEvidence: [
                'categories' => [
                    'vat' => [
                        'amount' => 3000,
                    ],
                ],
            ],
        );

        $evidence = new StatutorySettlementEvidence(
            categories: [
                'vat' => [
                    'amount' => 3000,
                    'transactions' => 1,
                ],
            ],
            totalObservedAmount: 3000,
            paymentEvidenceExists: true,
        );

        $result = app(ObligationReconciliationService::class)
            ->reconcile($assessment, $evidence);

        $this->assertEqualsWithDelta(
            4523.30,
            $result->reportedTotal,
            0.001
        );

        $this->assertEqualsWithDelta(
            3000.0,
            $result->paymentsObserved,
            0.001
        );

        $this->assertEqualsWithDelta(
            1523.30,
            $result->unresolvedDifference,
            0.001
        );

        $this->assertSame(
            'medium',
            $result->confidence
        );
    }
}
