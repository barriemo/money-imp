<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

final class ObligationReconciliationService
{
    public function reconcile(
        LiabilityAssessment $assessment,
        StatutorySettlementEvidence $settlementEvidence,
    ): ObligationReconciliation {

        $categories = [];

        foreach ($settlementEvidence->categories as $type => $payment) {
            $categories[$type] = [
                'payments_observed' => $payment['amount'],
                'transactions' => $payment['transactions'],
            ];
        }

        $paymentsObserved = $settlementEvidence->totalObservedAmount;

        $difference = max(
            0,
            $assessment->currentReportedExposure - $paymentsObserved
        );

        return new ObligationReconciliation(
            categories: $categories,
            reportedTotal: $assessment->currentReportedExposure,
            paymentsObserved: $paymentsObserved,
            unresolvedDifference: $difference,
            confidence: $settlementEvidence->paymentEvidenceExists
                ? 'medium'
                : 'low',
        );
    }
}
