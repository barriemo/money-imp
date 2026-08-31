<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

class LiabilityAssessment
{
    public function __construct(
        public readonly float $reportedTotal,
        public readonly float $currentReportedExposure,
        public readonly float $reportedOverdue,
        public readonly float $reportedUpcoming,
        public readonly float $historicalReportedUnresolved,
        public readonly float $settlementUnresolved,
        public readonly bool $bankTransactionEvidenceCurrent,
        public readonly bool $canInferPaymentAbsence,
        public readonly array $unknownCategories,
        public readonly array $currentItems,
    ) {}

    public function toArray(): array
    {
        return [
            'reported_total' => $this->reportedTotal,

            'current_reported_exposure' => $this->currentReportedExposure,

            'reported_overdue' => $this->reportedOverdue,

            'reported_upcoming' => $this->reportedUpcoming,

            'historical_reported_unresolved' => $this->historicalReportedUnresolved,

            'settlement_unresolved' => $this->settlementUnresolved,

            'bank_transaction_evidence_current' => $this->bankTransactionEvidenceCurrent,

            'can_infer_payment_absence' => $this->canInferPaymentAbsence,

            'unknown_categories' => $this->unknownCategories,

            'current_items' => $this->currentItems,
        ];
    }
}
