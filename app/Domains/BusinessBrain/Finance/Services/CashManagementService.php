<?php

namespace App\Domains\BusinessBrain\Finance\Services;

use App\Domains\BusinessBrain\Finance\DTOs\CashRiskAssessment;

class CashManagementService
{
    public function assess(
        CashRiskAssessment $assessment
    ): array {
        return [
            'risk_score' => $assessment->riskScore(),
            'confidence' => $assessment->confidence,
            'evidence' => $assessment->evidence,
            'recommendations' => $this->recommendations(
                $assessment
            ),
        ];
    }

    protected function recommendations(
        CashRiskAssessment $assessment
    ): array {
        $recommendations = [];

        if (
            $assessment->unknownExposure > 0
        ) {
            $recommendations[] =
                'Review unknown liabilities and confirm exposure.';
        }

        if (
            $assessment->outstandingInvoices >
            $assessment->cashPosition
        ) {
            $recommendations[] =
                'Prioritise overdue invoice collection.';
        }

        if (
            $assessment->knownLiabilities >
            $assessment->cashPosition
        ) {
            $recommendations[] =
                'Review upcoming liabilities against available cash.';
        }

        return $recommendations;
    }
}
