<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BankTruth\BankEvidenceCoverage;
use App\Domains\BusinessBrain\ObligationTruth\LiabilityAssessmentService;
use App\Models\Liability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiabilityAssessmentServiceTest extends TestCase
{
    public function test_current_exposure_does_not_sum_all_historical_source_reports(): void
    {
        $liabilities = collect([
            $this->reportedVat(
                amount: 3562.81,
                dueDate: '2025-12-07',
                periodEnd: '2025-10-31',
            ),

            $this->reportedVat(
                amount: 4523.30,
                dueDate: '2026-06-07',
                periodEnd: '2026-04-30',
            ),

            $this->reportedVat(
                amount: 5777.67,
                dueDate: '2026-09-07',
                periodEnd: '2026-07-31',
            ),
        ]);

        $assessment = app(
            LiabilityAssessmentService::class
        )->assess(
            liabilities: $liabilities,

            bankCoverage: new BankEvidenceCoverage(
                latestTransactionDate: '2026-07-31',

                latestBalanceAt: '2026-08-11 08:09:03',

                daysSinceLatestTransaction: 31,

                daysSinceLatestBalance: 20,

                transactionEvidenceCurrent: false,

                balanceEvidenceCurrent: false,
            ),

            asOf: CarbonImmutable::parse(
                '2026-08-31'
            ),
        );

        $this->assertEqualsWithDelta(
            13863.78,
            $assessment->reportedTotal,
            0.001
        );

        $this->assertEqualsWithDelta(
            10300.97,
            $assessment
                ->currentReportedExposure,
            0.001
        );

        $this->assertEqualsWithDelta(
            4523.30,
            $assessment->reportedOverdue,
            0.001
        );

        $this->assertEqualsWithDelta(
            5777.67,
            $assessment->reportedUpcoming,
            0.001
        );

        $this->assertEqualsWithDelta(
            3562.81,
            $assessment
                ->historicalReportedUnresolved,
            0.001
        );

        $this->assertEqualsWithDelta(
            4523.30,
            $assessment
                ->settlementUnresolved,
            0.001
        );

        $this->assertFalse(
            $assessment
                ->bankTransactionEvidenceCurrent
        );

        $this->assertFalse(
            $assessment
                ->canInferPaymentAbsence
        );

        $this->assertSame(
            [
                'paye',
                'corporation_tax',
            ],
            $assessment->unknownCategories
        );

        $this->assertCount(
            2,
            $assessment->currentItems
        );

        $this->assertSame(
            'reported_overdue',
            $assessment
                ->currentItems[0]['assessment']
        );

        $this->assertSame(
            'reported_upcoming',
            $assessment
                ->currentItems[1]['assessment']
        );
    }

    public function test_verified_liability_is_not_relabelled_as_reported_evidence(): void
    {
        $liability = $this->reportedVat(
            amount: 1000,
            dueDate: '2026-09-07',
            periodEnd: '2026-07-31',
        );

        $liability->verified = true;
        $liability->confidence = 100;

        $assessment = app(
            LiabilityAssessmentService::class
        )->assess(
            liabilities: collect([$liability]),

            bankCoverage: new BankEvidenceCoverage(
                latestTransactionDate: '2026-08-30',

                latestBalanceAt: '2026-08-30 08:00:00',

                daysSinceLatestTransaction: 1,

                daysSinceLatestBalance: 1,

                transactionEvidenceCurrent: true,

                balanceEvidenceCurrent: true,
            ),

            asOf: CarbonImmutable::parse(
                '2026-08-31'
            ),
        );

        $this->assertSame(
            0.0,
            $assessment->reportedTotal
        );

        $this->assertSame(
            0.0,
            $assessment
                ->currentReportedExposure
        );
    }

    private function reportedVat(
        float $amount,
        string $dueDate,
        string $periodEnd
    ): Liability {
        $liability = new Liability;

        $liability->id =
            (string) Str::uuid();

        $liability->type =
            'vat';

        $liability->name =
            'FreeAgent VAT '.$periodEnd;

        $liability->amount =
            $amount;

        $liability->due_date =
            $dueDate;

        $liability->status =
            'open';

        $liability->source =
            'freeagent_vat_return';

        $liability->verified =
            false;

        $liability->confidence =
            90;

        $liability->metadata = [
            'period_ends_on' => $periodEnd,

            'reported_by_freeagent' => true,

            'settlement_verified' => false,
        ];

        return $liability;
    }
}
