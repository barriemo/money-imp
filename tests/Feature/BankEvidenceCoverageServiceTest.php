<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BankTruth\BankEvidenceCoverageService;
use Tests\TestCase;

class BankEvidenceCoverageServiceTest extends TestCase
{
    public function test_stale_transaction_coverage_cannot_prove_payment_absence(): void
    {
        $coverage = app(
            BankEvidenceCoverageService::class
        )->fromDates(
            latestTransactionDate: '2026-07-31',

            latestBalanceAt: '2026-08-11 08:09:03',

            asOf: '2026-08-31 12:00:00',
        );

        $this->assertSame(
            '2026-07-31',
            $coverage->latestTransactionDate
        );

        $this->assertSame(
            31,
            $coverage->daysSinceLatestTransaction
        );

        $this->assertSame(
            20,
            $coverage->daysSinceLatestBalance
        );

        $this->assertFalse(
            $coverage->transactionEvidenceCurrent
        );

        $this->assertFalse(
            $coverage->balanceEvidenceCurrent
        );

        $this->assertFalse(
            $coverage->canInferPaymentAbsence()
        );
    }

    public function test_recent_transaction_coverage_can_support_absence_reasoning(): void
    {
        $coverage = app(
            BankEvidenceCoverageService::class
        )->fromDates(
            latestTransactionDate: '2026-08-29',

            latestBalanceAt: '2026-08-30 08:00:00',

            asOf: '2026-08-31 12:00:00',
        );

        $this->assertTrue(
            $coverage->transactionEvidenceCurrent
        );

        $this->assertTrue(
            $coverage->balanceEvidenceCurrent
        );

        $this->assertTrue(
            $coverage->canInferPaymentAbsence()
        );
    }

    public function test_missing_transaction_evidence_never_proves_absence(): void
    {
        $coverage = app(
            BankEvidenceCoverageService::class
        )->fromDates(
            latestTransactionDate: null,
            latestBalanceAt: null,
            asOf: '2026-08-31',
        );

        $this->assertNull(
            $coverage->daysSinceLatestTransaction
        );

        $this->assertFalse(
            $coverage->transactionEvidenceCurrent
        );

        $this->assertFalse(
            $coverage->canInferPaymentAbsence()
        );
    }
}
