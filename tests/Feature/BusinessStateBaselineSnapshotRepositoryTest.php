<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineSnapshotRepository;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessStateBaselineSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_preserves_unknown_zero_and_provenance(): void
    {
        $repository =
            app(
                BusinessStateBaselineSnapshotRepository::class
            );

        $repository->store(
            new BusinessStateBaseline(
                metrics: collect([
                    $this->metric(
                        metric: 'safe_available_cash',

                        source: 'financial.cash.safeAvailableCash',

                        known: false,

                        value: null
                    ),

                    $this->metric(
                        metric: 'known_liability_exposure',

                        source: 'financial.liabilities.known',

                        known: true,

                        value: 0
                    ),
                ]),

                asOf: CarbonImmutable::parse(
                    '2026-09-04 12:00:00'
                )
            )
        );

        $loaded =
            $repository->latestBefore(
                CarbonImmutable::parse(
                    '2026-09-04 13:00:00'
                )
            );

        $this->assertNotNull(
            $loaded
        );

        $this->assertSame(
            '2026-09-04T12:00:00+00:00',
            $loaded
                ->asOf
                ->toIso8601String()
        );

        $metrics =
            $loaded->metrics
                ->keyBy(
                    'metric'
                );

        $safeCash =
            $metrics->get(
                'safe_available_cash'
            );

        $this->assertFalse(
            $safeCash->known
        );

        $this->assertNull(
            $safeCash->value
        );

        $this->assertSame(
            'financial.cash.safeAvailableCash',
            $safeCash->source
        );

        $liability =
            $metrics->get(
                'known_liability_exposure'
            );

        $this->assertTrue(
            $liability->known
        );

        $this->assertEquals(
            0,
            $liability->value
        );

        $this->assertSame(
            'financial.liabilities.known',
            $liability->source
        );

        $this->assertDatabaseCount(
            'business_state_baseline_snapshot_records',
            1
        );
    }

    public function test_latest_before_never_uses_same_time_or_future_baseline(): void
    {
        $repository =
            app(
                BusinessStateBaselineSnapshotRepository::class
            );

        $repository->store(
            $this->baseline(
                '2026-09-04 12:00:00',
                1000
            )
        );

        $repository->store(
            $this->baseline(
                '2026-09-04 14:00:00',
                2000
            )
        );

        $loaded =
            $repository->latestBefore(
                CarbonImmutable::parse(
                    '2026-09-04 13:00:00'
                )
            );

        $this->assertNotNull(
            $loaded
        );

        $this->assertSame(
            '2026-09-04T12:00:00+00:00',
            $loaded
                ->asOf
                ->toIso8601String()
        );

        $this->assertEquals(
            1000,
            $loaded->metrics
                ->first()
                ->value
        );

        $this->assertNull(
            $repository->latestBefore(
                CarbonImmutable::parse(
                    '2026-09-04 12:00:00'
                )
            )
        );
    }

    private function baseline(
        string $asOf,
        int|float $value,
    ): BusinessStateBaseline {
        return new BusinessStateBaseline(
            metrics: collect([
                $this->metric(
                    metric: 'outstanding_invoiced_revenue',

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

    private function metric(
        string $metric,
        string $source,
        bool $known,
        int|float|null $value,
    ): BusinessStateMetric {
        return new BusinessStateMetric(
            domain: 'test',

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
