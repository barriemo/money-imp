<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeDetector;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class BusinessStateChangeDetectorTest extends TestCase
{
    public function test_unknown_to_known_is_not_treated_as_zero_to_value(): void
    {
        $previous =
            $this->baseline(
                '2026-09-04 12:00:00',
                [
                    $this->metric(
                        domain: 'cash',
                        metric: 'safe_available_cash',
                        source: 'financial.cash.safeAvailableCash',
                        known: false,
                        value: null
                    ),
                    $this->metric(
                        domain: 'receivables',
                        metric: 'ledger_outstanding',
                        source: 'financial.receivables.ledgerOutstanding',
                        known: true,
                        value: 0
                    ),
                ]
            );

        $current =
            $this->baseline(
                '2026-09-04 13:00:00',
                [
                    $this->metric(
                        domain: 'cash',
                        metric: 'safe_available_cash',
                        source: 'financial.cash.safeAvailableCash',
                        known: true,
                        value: 10000
                    ),
                    $this->metric(
                        domain: 'receivables',
                        metric: 'ledger_outstanding',
                        source: 'financial.receivables.ledgerOutstanding',
                        known: true,
                        value: 10000
                    ),
                ]
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

        $safeCash =
            $byMetric->get(
                'safe_available_cash'
            );

        $receivables =
            $byMetric->get(
                'ledger_outstanding'
            );

        $this->assertSame(
            BusinessStateChange::BECAME_KNOWN,
            $safeCash->kind
        );

        $this->assertNull(
            $safeCash->previous->value
        );

        $this->assertSame(
            10000,
            $safeCash->current->value
        );

        $this->assertSame(
            BusinessStateChange::INCREASED,
            $receivables->kind
        );

        $this->assertSame(
            0,
            $receivables->previous->value
        );

        $this->assertSame(
            10000,
            $receivables->current->value
        );
    }

    public function test_known_to_unknown_is_not_treated_as_a_decrease(): void
    {
        $changes =
            (
                new BusinessStateChangeDetector
            )->compare(
                previous: $this->baseline(
                    '2026-09-04 12:00:00',
                    [
                        $this->metric(
                            domain: 'cash',
                            metric: 'safe_available_cash',
                            source: 'financial.cash.safeAvailableCash',
                            known: true,
                            value: 5000
                        ),
                    ]
                ),
                current: $this->baseline(
                    '2026-09-04 13:00:00',
                    [
                        $this->metric(
                            domain: 'cash',
                            metric: 'safe_available_cash',
                            source: 'financial.cash.safeAvailableCash',
                            known: false,
                            value: null
                        ),
                    ]
                )
            );

        $this->assertCount(
            1,
            $changes
        );

        $change =
            $changes->first();

        $this->assertSame(
            BusinessStateChange::BECAME_UNKNOWN,
            $change->kind
        );

        $this->assertSame(
            5000,
            $change->previous->value
        );

        $this->assertNull(
            $change->current->value
        );
    }

    public function test_numeric_change_is_directional_without_health_judgement(): void
    {
        $previous =
            $this->baseline(
                '2026-09-04 12:00:00',
                [
                    $this->metric(
                        domain: 'revenue',
                        metric: 'outstanding',
                        source: 'revenue.outstanding',
                        known: true,
                        value: 1000
                    ),
                    $this->metric(
                        domain: 'delivery',
                        metric: 'unrecovered_work_value',
                        source: 'revenue.unrecoveredWorkValue',
                        known: true,
                        value: 2000
                    ),
                    $this->metric(
                        domain: 'revenue',
                        metric: 'gross_invoiced',
                        source: 'revenue.grossInvoiced',
                        known: true,
                        value: 10000
                    ),
                    $this->metric(
                        domain: 'cash',
                        metric: 'safe_available_cash',
                        source: 'financial.cash.safeAvailableCash',
                        known: false,
                        value: null
                    ),
                ]
            );

        $current =
            $this->baseline(
                '2026-09-04 13:00:00',
                [
                    $this->metric(
                        domain: 'revenue',
                        metric: 'outstanding',
                        source: 'revenue.outstanding',
                        known: true,
                        value: 1500
                    ),
                    $this->metric(
                        domain: 'delivery',
                        metric: 'unrecovered_work_value',
                        source: 'revenue.unrecoveredWorkValue',
                        known: true,
                        value: 500
                    ),
                    $this->metric(
                        domain: 'revenue',
                        metric: 'gross_invoiced',
                        source: 'revenue.grossInvoiced',
                        known: true,
                        value: 10000
                    ),
                    $this->metric(
                        domain: 'cash',
                        metric: 'safe_available_cash',
                        source: 'financial.cash.safeAvailableCash',
                        known: false,
                        value: null
                    ),
                ]
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

        $this->assertCount(
            2,
            $changes
        );

        $this->assertSame(
            BusinessStateChange::INCREASED,
            $byMetric
                ->get('outstanding')
                ->kind
        );

        $this->assertSame(
            BusinessStateChange::DECREASED,
            $byMetric
                ->get('unrecovered_work_value')
                ->kind
        );

        $this->assertFalse(
            $byMetric->has(
                'gross_invoiced'
            )
        );

        $this->assertFalse(
            $byMetric->has(
                'safe_available_cash'
            )
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

    public function test_changed_metric_set_fails_closed(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'metric sets differ'
        );

        (
            new BusinessStateChangeDetector
        )->compare(
            previous: $this->baseline(
                '2026-09-04 12:00:00',
                [
                    $this->metric(
                        domain: 'cash',
                        metric: 'safe_available_cash',
                        source: 'financial.cash.safeAvailableCash',
                        known: true,
                        value: 1000
                    ),
                ]
            ),
            current: $this->baseline(
                '2026-09-04 13:00:00',
                [
                    $this->metric(
                        domain: 'cash',
                        metric: 'safe_available_cash',
                        source: 'financial.cash.safeAvailableCash',
                        known: true,
                        value: 1000
                    ),
                    $this->metric(
                        domain: 'revenue',
                        metric: 'outstanding',
                        source: 'revenue.outstanding',
                        known: true,
                        value: 0
                    ),
                ]
            )
        );
    }

    public function test_changed_metric_source_fails_closed(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'metric source changed'
        );

        (
            new BusinessStateChangeDetector
        )->compare(
            previous: $this->baseline(
                '2026-09-04 12:00:00',
                [
                    $this->metric(
                        domain: 'cash',
                        metric: 'safe_available_cash',
                        source: 'financial.cash.safeAvailableCash',
                        known: true,
                        value: 1000
                    ),
                ]
            ),
            current: $this->baseline(
                '2026-09-04 13:00:00',
                [
                    $this->metric(
                        domain: 'cash',
                        metric: 'safe_available_cash',
                        source: 'other.cash.source',
                        known: true,
                        value: 1000
                    ),
                ]
            )
        );
    }

    public function test_non_forward_baseline_comparison_fails_closed(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'must be later'
        );

        $baseline =
            $this->baseline(
                '2026-09-04 12:00:00',
                [
                    $this->metric(
                        domain: 'cash',
                        metric: 'safe_available_cash',
                        source: 'financial.cash.safeAvailableCash',
                        known: true,
                        value: 1000
                    ),
                ]
            );

        (
            new BusinessStateChangeDetector
        )->compare(
            previous: $baseline,
            current: $baseline
        );
    }

    public function test_metric_truth_state_rejects_unknown_with_value(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Unknown business state metrics cannot carry a value'
        );

        $this->metric(
            domain: 'cash',
            metric: 'safe_available_cash',
            source: 'financial.cash.safeAvailableCash',
            known: false,
            value: 0
        );
    }

    public function test_metric_truth_state_rejects_known_without_value(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Known business state metrics require a value'
        );

        $this->metric(
            domain: 'cash',
            metric: 'safe_available_cash',
            source: 'financial.cash.safeAvailableCash',
            known: true,
            value: null
        );
    }

    private function baseline(
        string $asOf,
        array $metrics,
    ): BusinessStateBaseline {
        return new BusinessStateBaseline(
            metrics: collect(
                $metrics
            ),
            asOf: CarbonImmutable::parse(
                $asOf
            )
        );
    }

    private function metric(
        string $domain,
        string $metric,
        string $source,
        bool $known,
        int|float|null $value,
    ): BusinessStateMetric {
        return new BusinessStateMetric(
            domain: $domain,
            metric: $metric,
            scope: 'business',
            clientId: null,
            client: null,
            source: $source,
            known: $known,
            value: $value
        );
    }
}
