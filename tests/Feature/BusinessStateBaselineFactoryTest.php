<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGaps;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineFactory;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeDetector;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use App\Domains\BusinessBrain\CashTruth\CashTruth;
use App\Domains\BusinessBrain\CreditTruth\CreditTruth;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\FinancialPosition\LiabilityPosition;
use App\Domains\BusinessBrain\FinancialPosition\ReceivablesPosition;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BusinessStateBaselineFactoryTest extends TestCase
{
    public function test_factory_extracts_fixed_business_level_metrics_without_inference(): void
    {
        $baseline =
            (
                new BusinessStateBaselineFactory
            )->fromState(
                $this->state(
                    asOf: '2026-09-04 12:00:00',

                    safeAvailableCash: null,

                    verifiedCollectible: null,

                    knownLiabilityExposure: 1000,

                    liabilityCoverageComplete: false,

                    outstanding: 3000,

                    unverifiedBankAccountCount: 2
                )
            );

        $this->assertSame(
            '2026-09-04T12:00:00+00:00',
            $baseline
                ->asOf
                ->toIso8601String()
        );

        $this->assertSame(
            BusinessStateMetricCatalog::ALL,
            $baseline
                ->metrics
                ->pluck('metric')
                ->all()
        );

        $this->assertCount(
            19,
            $baseline->metrics
        );

        $this->assertTrue(
            $baseline->metrics
                ->every(
                    fn (BusinessStateMetric $metric): bool => $metric->scope === 'business'
                        && $metric->clientId === null
                        && $metric->client === null
                )
        );

        $metrics =
            $baseline->metrics
                ->keyBy('metric');

        $safeCash =
            $metrics->get(
                BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH
            );

        $this->assertFalse(
            $safeCash->known
        );

        $this->assertNull(
            $safeCash->value
        );

        $verifiedCollectible =
            $metrics->get(
                BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES
            );

        $this->assertFalse(
            $verifiedCollectible->known
        );

        $this->assertNull(
            $verifiedCollectible->value
        );

        $knownLiability =
            $metrics->get(
                BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE
            );

        $this->assertTrue(
            $knownLiability->known
        );

        $this->assertSame(
            1000.0,
            $knownLiability->value
        );

        $totalLiability =
            $metrics->get(
                BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE
            );

        $this->assertFalse(
            $totalLiability->known
        );

        $this->assertNull(
            $totalLiability->value
        );

        $this->assertSame(
            'financial.liabilities.known',
            $totalLiability->source
        );

        $this->assertSame(
            2,
            $metrics
                ->get(
                    BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS
                )
                ->value
        );

        $this->assertSame(
            3000.0,
            $metrics
                ->get(
                    BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE
                )
                ->value
        );
    }

    public function test_authoritative_zero_remains_known_zero(): void
    {
        $baseline =
            (
                new BusinessStateBaselineFactory
            )->fromState(
                $this->state(
                    asOf: '2026-09-04 12:00:00',

                    safeAvailableCash: 0,

                    verifiedCollectible: 0,

                    knownLiabilityExposure: 0,

                    liabilityCoverageComplete: true,

                    outstanding: 0,

                    unverifiedBankAccountCount: 0
                )
            );

        $metrics =
            $baseline->metrics
                ->keyBy('metric');

        foreach (
            [
                BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,
                BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES,
                BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE,
                BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE,
                BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,
                BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS,
            ] as $metricName
        ) {
            $metric =
                $metrics->get(
                    $metricName
                );

            $this->assertTrue(
                $metric->known,
                $metricName
            );

            $this->assertEquals(
                0,
                $metric->value,
                $metricName
            );
        }
    }

    public function test_extracted_baselines_integrate_with_truth_preserving_change_detection(): void
    {
        $factory =
            new BusinessStateBaselineFactory;

        $previous =
            $factory->fromState(
                $this->state(
                    asOf: '2026-09-04 12:00:00',

                    safeAvailableCash: null,

                    verifiedCollectible: null,

                    knownLiabilityExposure: 0,

                    liabilityCoverageComplete: false,

                    outstanding: 1000,

                    unverifiedBankAccountCount: 2
                )
            );

        $current =
            $factory->fromState(
                $this->state(
                    asOf: '2026-09-04 13:00:00',

                    safeAvailableCash: 0,

                    verifiedCollectible: null,

                    knownLiabilityExposure: 0,

                    liabilityCoverageComplete: true,

                    outstanding: 1500,

                    unverifiedBankAccountCount: 1
                )
            );

        $changes =
            (
                new BusinessStateChangeDetector
            )->compare(
                previous: $previous,
                current: $current
            );

        $byMetric =
            $changes->keyBy(
                fn (BusinessStateChange $change): string => $change->current->metric
            );

        $this->assertSame(
            BusinessStateChange::BECAME_KNOWN,
            $byMetric
                ->get(
                    BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH
                )
                ->kind
        );

        $this->assertSame(
            0.0,
            $byMetric
                ->get(
                    BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH
                )
                ->current
                ->value
        );

        $this->assertSame(
            BusinessStateChange::BECAME_KNOWN,
            $byMetric
                ->get(
                    BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE
                )
                ->kind
        );

        $this->assertSame(
            BusinessStateChange::INCREASED,
            $byMetric
                ->get(
                    BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE
                )
                ->kind
        );

        $this->assertSame(
            BusinessStateChange::DECREASED,
            $byMetric
                ->get(
                    BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS
                )
                ->kind
        );

        $this->assertFalse(
            property_exists(
                $changes->first(),
                'improved'
            )
        );

        $this->assertFalse(
            property_exists(
                $changes->first(),
                'worsened'
            )
        );
    }

    private function state(
        string $asOf,
        ?float $safeAvailableCash,
        ?float $verifiedCollectible,
        float $knownLiabilityExposure,
        bool $liabilityCoverageComplete,
        float $outstanding,
        int $unverifiedBankAccountCount,
    ): BusinessState {
        $time =
            CarbonImmutable::parse(
                $asOf
            );

        $cashConfidence =
            $safeAvailableCash === null
                ? 0
                : 100;

        $receivableConfidence =
            $verifiedCollectible === null
                ? 50
                : 100;

        $liabilityConfidence =
            $liabilityCoverageComplete
                ? 100
                : 0;

        return new BusinessState(
            financial: new FinancialPosition(
                cash: new CashTruth(
                    accountCount: 1
                        + $unverifiedBankAccountCount,

                    verifiedAccountCount: 1,

                    freshAccountCount: 1,

                    staleAccountCount: 1,

                    unverifiedAccountCount: $unverifiedBankAccountCount,

                    verifiedCash: 2000,

                    reportedAccountingBalance: 4000,

                    reportedUnverifiedCardDebt: 0,

                    creditCardDebt: 0,

                    knownLiabilities: $knownLiabilityExposure,

                    knownNetPosition: 1500,

                    safeAvailableCash: $safeAvailableCash,

                    ledgerReceivables: $outstanding,

                    paymentsWaitingAllocation: 500,

                    bankVerificationConfidence: $cashConfidence,

                    bankFreshnessConfidence: $cashConfidence,

                    liabilityConfidence: $liabilityConfidence,

                    receivableConfidence: $receivableConfidence,

                    cashConfidence: $cashConfidence,

                    oldestBalanceAt: null,

                    newestBalanceAt: null
                ),

                receivables: new ReceivablesPosition(
                    ledgerOutstanding: $outstanding,

                    paymentsWaitingAllocation: 500,

                    verifiedCollectible: $verifiedCollectible,

                    confidence: $receivableConfidence
                ),

                liabilities: new LiabilityPosition(
                    known: $knownLiabilityExposure,

                    vat: 0,

                    paye: 0,

                    other: 0,

                    confidence: $liabilityConfidence,

                    coverageComplete: $liabilityCoverageComplete
                ),

                credit: new CreditTruth(
                    facilities: collect(),

                    facilityCount: 0,

                    verifiedFacilityCount: 0,

                    reportedExposure: 0,

                    verifiedExposure: 0,

                    reportedAvailableCredit: 0,

                    minimumPaymentsDue: 0,

                    confidence: 0
                ),

                confidence: 0,

                asOf: $time
            ),

            revenue: new RevenueTruthSummary(
                clientCount: 2,

                grossInvoiced: 10000,

                paidAccordingToAccounting: 7000,

                outstanding: $outstanding,

                unrecoveredWorkValue: 1250,

                bankVerifiedPaymentValue: 6500,

                clientsWithOutstandingRevenue: $outstanding > 0
                        ? 1
                        : 0,

                clientsWithWeakPaymentEvidence: 1,

                clientsWithoutWorkEvidence: 2,

                averageCommercialConfidence: 75,

                gaps: collect()
            ),

            clients: collect(),

            gaps: new BusinessStateGaps(
                unknowns: collect(),

                evidenceGaps: collect()
            ),

            asOf: $time
        );
    }
}
