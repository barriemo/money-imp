<?php

namespace App\Domains\BusinessBrain\FinancialPosition;

class LiabilityPosition
{
    public function __construct(
        public float $known,
        public float $vat,
        public float $paye,
        public float $other,
        public int $confidence,
        public bool $coverageComplete,
        public float $employerNic = 0,
        public float $payroll = 0,
        public float $reported = 0,
        public float $currentReportedExposure = 0,
        public float $reportedOverdue = 0,
        public float $reportedUpcoming = 0,
        public float $historicalReportedUnresolved = 0,
        public float $settlementUnresolved = 0,
        public bool $bankTransactionEvidenceCurrent = false,
        public bool $canInferPaymentAbsence = false,
        public array $unknownCategories = [],
        public array $reportedItems = []
    ) {}
}
