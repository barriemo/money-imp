<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGaps;
use App\Domains\BusinessBrain\BusinessState\BusinessStateService;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineFactory;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineSnapshotRepository;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeDetector;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidenceService;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidenceSet;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationPolicy;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationReportService;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class BusinessStateExplanationReportServiceTest extends TestCase
{
    public function test_no_previous_baseline_means_explanation_comparison_is_unavailable(): void
    {
        $state =
            $this->state();

        $current =
            $this->baseline(
                asOf: '2026-09-04 13:00:00',

                value: 120000
            );

        $states =
            Mockery::mock(
                BusinessStateService::class
            );

        $factory =
            Mockery::mock(
                BusinessStateBaselineFactory::class
            );

        $repository =
            Mockery::mock(
                BusinessStateBaselineSnapshotRepository::class
            );

        $detector =
            Mockery::mock(
                BusinessStateChangeDetector::class
            );

        $evidence =
            Mockery::mock(
                BusinessStateExplanationEvidenceService::class
            );

        $policy =
            Mockery::mock(
                BusinessStateExplanationPolicy::class
            );

        $states
            ->shouldReceive(
                'current'
            )
            ->once()
            ->andReturn(
                $state
            );

        $factory
            ->shouldReceive(
                'fromState'
            )
            ->once()
            ->with(
                $state
            )
            ->andReturn(
                $current
            );

        $repository
            ->shouldReceive(
                'latestBefore'
            )
            ->once()
            ->with(
                $current->asOf
            )
            ->andReturn(
                null
            );

        $detector
            ->shouldNotReceive(
                'compare'
            );

        $evidence
            ->shouldNotReceive(
                'forChange'
            );

        $policy
            ->shouldNotReceive(
                'assess'
            );

        $report =
            (
                new BusinessStateExplanationReportService(
                    states: $states,

                    factory: $factory,

                    repository: $repository,

                    detector: $detector,

                    evidence: $evidence,

                    policy: $policy
                )
            )->current();

        $this->assertFalse(
            $report->hasComparisonBaseline()
        );

        $this->assertNull(
            $report->previous
        );

        $this->assertTrue(
            $report->explanations
                ->isEmpty()
        );
    }

    public function test_same_current_business_state_is_used_for_change_and_explanation_evidence(): void
    {
        $state =
            $this->state();

        $previous =
            $this->baseline(
                asOf: '2026-09-04 12:00:00',

                value: 100000
            );

        $current =
            $this->baseline(
                asOf: '2026-09-04 13:00:00',

                value: 120000
            );

        $change =
            new BusinessStateChange(
                previous: $previous->metrics
                    ->first(),

                current: $current->metrics
                    ->first(),

                kind: BusinessStateChange::INCREASED,

                previousAsOf: $previous->asOf,

                currentAsOf: $current->asOf
            );

        $set =
            Mockery::mock(
                BusinessStateExplanationEvidenceSet::class
            );

        $explanation =
            Mockery::mock(
                BusinessStateExplanation::class
            );

        $states =
            Mockery::mock(
                BusinessStateService::class
            );

        $factory =
            Mockery::mock(
                BusinessStateBaselineFactory::class
            );

        $repository =
            Mockery::mock(
                BusinessStateBaselineSnapshotRepository::class
            );

        $detector =
            Mockery::mock(
                BusinessStateChangeDetector::class
            );

        $evidence =
            Mockery::mock(
                BusinessStateExplanationEvidenceService::class
            );

        $policy =
            Mockery::mock(
                BusinessStateExplanationPolicy::class
            );

        $states
            ->shouldReceive(
                'current'
            )
            ->once()
            ->andReturn(
                $state
            );

        $factory
            ->shouldReceive(
                'fromState'
            )
            ->once()
            ->with(
                $state
            )
            ->andReturn(
                $current
            );

        $repository
            ->shouldReceive(
                'latestBefore'
            )
            ->once()
            ->with(
                $current->asOf
            )
            ->andReturn(
                $previous
            );

        $detector
            ->shouldReceive(
                'compare'
            )
            ->once()
            ->with(
                $previous,
                $current
            )
            ->andReturn(
                collect([
                    $change,
                ])
            );

        /*
         * This exact object identity is the 5.3D temporal guarantee:
         * explanation evidence sees the same state that produced the
         * current comparison baseline.
         */
        $evidence
            ->shouldReceive(
                'forChange'
            )
            ->once()
            ->with(
                $change,
                $state
            )
            ->andReturn(
                $set
            );

        $policy
            ->shouldReceive(
                'assess'
            )
            ->once()
            ->with(
                $set
            )
            ->andReturn(
                $explanation
            );

        $report =
            (
                new BusinessStateExplanationReportService(
                    states: $states,

                    factory: $factory,

                    repository: $repository,

                    detector: $detector,

                    evidence: $evidence,

                    policy: $policy
                )
            )->current();

        $this->assertTrue(
            $report->hasComparisonBaseline()
        );

        $this->assertSame(
            $previous,
            $report->previous
        );

        $this->assertCount(
            1,
            $report->explanations
        );

        $this->assertSame(
            $explanation,
            $report->explanations
                ->first()
        );
    }

    public function test_no_changes_means_no_evidence_or_policy_work_is_invented(): void
    {
        $state =
            $this->state();

        $previous =
            $this->baseline(
                asOf: '2026-09-04 12:00:00',

                value: 120000
            );

        $current =
            $this->baseline(
                asOf: '2026-09-04 13:00:00',

                value: 120000
            );

        $states =
            Mockery::mock(
                BusinessStateService::class
            );

        $factory =
            Mockery::mock(
                BusinessStateBaselineFactory::class
            );

        $repository =
            Mockery::mock(
                BusinessStateBaselineSnapshotRepository::class
            );

        $detector =
            Mockery::mock(
                BusinessStateChangeDetector::class
            );

        $evidence =
            Mockery::mock(
                BusinessStateExplanationEvidenceService::class
            );

        $policy =
            Mockery::mock(
                BusinessStateExplanationPolicy::class
            );

        $states
            ->shouldReceive(
                'current'
            )
            ->once()
            ->andReturn(
                $state
            );

        $factory
            ->shouldReceive(
                'fromState'
            )
            ->once()
            ->with(
                $state
            )
            ->andReturn(
                $current
            );

        $repository
            ->shouldReceive(
                'latestBefore'
            )
            ->once()
            ->andReturn(
                $previous
            );

        $detector
            ->shouldReceive(
                'compare'
            )
            ->once()
            ->with(
                $previous,
                $current
            )
            ->andReturn(
                collect()
            );

        $evidence
            ->shouldNotReceive(
                'forChange'
            );

        $policy
            ->shouldNotReceive(
                'assess'
            );

        $report =
            (
                new BusinessStateExplanationReportService(
                    states: $states,

                    factory: $factory,

                    repository: $repository,

                    detector: $detector,

                    evidence: $evidence,

                    policy: $policy
                )
            )->current();

        $this->assertTrue(
            $report->hasComparisonBaseline()
        );

        $this->assertTrue(
            $report->explanations
                ->isEmpty()
        );
    }

    private function state(): BusinessState
    {
        return new BusinessState(
            financial: Mockery::mock(
                FinancialPosition::class
            ),

            revenue: Mockery::mock(
                RevenueTruthSummary::class
            ),

            clients: collect(),

            gaps: new BusinessStateGaps(
                unknowns: collect(),

                evidenceGaps: collect()
            ),

            asOf: CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            )
        );
    }

    private function baseline(
        string $asOf,
        int|float $value,
    ): BusinessStateBaseline {
        return new BusinessStateBaseline(
            metrics: collect([
                new BusinessStateMetric(
                    domain: 'commercial',

                    metric: BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,

                    scope: 'business',

                    clientId: null,

                    client: null,

                    source: 'revenue.outstanding',

                    known: true,

                    value: $value
                ),
            ]),

            asOf: CarbonImmutable::parse(
                $asOf
            )
        );
    }
}
