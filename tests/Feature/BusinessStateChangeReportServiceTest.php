<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttention;
use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttentionPolicy;
use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateService;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineFactory;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineSnapshotRepository;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeDetector;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeReportService;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class BusinessStateChangeReportServiceTest extends TestCase
{
    public function test_no_previous_baseline_is_explicit_and_not_reported_as_no_change(): void
    {
        $current =
            $this->baseline(
                '2026-09-04 13:00:00',
                safeCashKnown: true,
                safeCash: 5000,
                outstanding: 1000
            );

        $state =
            Mockery::mock(
                BusinessState::class
            );

        $states =
            Mockery::mock(
                BusinessStateService::class
            );

        $states
            ->shouldReceive(
                'current'
            )
            ->once()
            ->andReturn(
                $state
            );

        $factory =
            Mockery::mock(
                BusinessStateBaselineFactory::class
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

        $repository =
            Mockery::mock(
                BusinessStateBaselineSnapshotRepository::class
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

        $repository
            ->shouldNotReceive(
                'store'
            );

        $report =
            (
                new BusinessStateChangeReportService(
                    states: $states,

                    factory: $factory,

                    repository: $repository,

                    detector: new BusinessStateChangeDetector,

                    attention: new BusinessStateChangeAttentionPolicy
                )
            )->current();

        $this->assertFalse(
            $report->hasComparisonBaseline()
        );

        $this->assertNull(
            $report->previous
        );

        $this->assertSame(
            $current,
            $report->current
        );

        $this->assertCount(
            0,
            $report->changes
        );

        $this->assertCount(
            0,
            $report->attention
        );
    }

    public function test_report_compares_previous_truth_and_selects_attention_without_writing(): void
    {
        $previous =
            $this->baseline(
                '2026-09-04 12:00:00',
                safeCashKnown: true,
                safeCash: 5000,
                outstanding: 1000
            );

        $current =
            $this->baseline(
                '2026-09-04 13:00:00',
                safeCashKnown: false,
                safeCash: null,
                outstanding: 1500
            );

        $state =
            Mockery::mock(
                BusinessState::class
            );

        $states =
            Mockery::mock(
                BusinessStateService::class
            );

        $states
            ->shouldReceive(
                'current'
            )
            ->once()
            ->andReturn(
                $state
            );

        $factory =
            Mockery::mock(
                BusinessStateBaselineFactory::class
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

        $repository =
            Mockery::mock(
                BusinessStateBaselineSnapshotRepository::class
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

        $repository
            ->shouldNotReceive(
                'store'
            );

        $report =
            (
                new BusinessStateChangeReportService(
                    states: $states,

                    factory: $factory,

                    repository: $repository,

                    detector: new BusinessStateChangeDetector,

                    attention: new BusinessStateChangeAttentionPolicy
                )
            )->current();

        $this->assertTrue(
            $report->hasComparisonBaseline()
        );

        $this->assertSame(
            $previous,
            $report->previous
        );

        $changes =
            $report->changes
                ->keyBy(
                    fn (BusinessStateChange $change): string => $change->current
                        ->metric
                );

        $this->assertSame(
            BusinessStateChange::BECAME_UNKNOWN,
            $changes
                ->get(
                    BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH
                )
                ->kind
        );

        $this->assertSame(
            BusinessStateChange::INCREASED,
            $changes
                ->get(
                    BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE
                )
                ->kind
        );

        $attentionTypes =
            $report->attention
                ->pluck(
                    'type'
                )
                ->sort()
                ->values()
                ->all();

        $this->assertSame(
            collect([
                BusinessStateChangeAttention::TRUTH_LOST,
                BusinessStateChangeAttention::FINANCIAL_EXPOSURE_INCREASED,
            ])
                ->sort()
                ->values()
                ->all(),
            $attentionTypes
        );
    }

    private function baseline(
        string $asOf,
        bool $safeCashKnown,
        int|float|null $safeCash,
        int|float $outstanding,
    ): BusinessStateBaseline {
        return new BusinessStateBaseline(
            metrics: collect([
                new BusinessStateMetric(
                    domain: 'cash',

                    metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                    scope: 'business',

                    clientId: null,

                    client: null,

                    source: 'financial.cash.safeAvailableCash',

                    known: $safeCashKnown,

                    value: $safeCash
                ),

                new BusinessStateMetric(
                    domain: 'commercial',

                    metric: BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,

                    scope: 'business',

                    clientId: null,

                    client: null,

                    source: 'revenue.outstanding',

                    known: true,

                    value: $outstanding
                ),
            ]),

            asOf: CarbonImmutable::parse(
                $asOf
            )
        );
    }
}
