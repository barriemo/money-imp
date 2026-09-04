<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttention;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeReport;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeReportService;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class BusinessChangesCommandTest extends TestCase
{
    public function test_changes_command_presents_factual_change_and_attention_without_decision_guidance(): void
    {
        $previous =
            $this->baseline(
                '2026-09-04 12:00:00',
                1000
            );

        $current =
            $this->baseline(
                '2026-09-04 13:00:00',
                1500
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

        $report =
            new BusinessStateChangeReport(
                current: $current,

                previous: $previous,

                changes: collect([
                    $change,
                ]),

                attention: collect([
                    new BusinessStateChangeAttention(
                        change: $change,

                        type: BusinessStateChangeAttention::FINANCIAL_EXPOSURE_INCREASED,

                        reason: 'Outstanding invoiced revenue increased.'
                    ),
                ])
            );

        $this->mock(
            BusinessStateChangeReportService::class,
            function (
                MockInterface $mock
            ) use ($report): void {
                $mock
                    ->shouldReceive(
                        'current'
                    )
                    ->once()
                    ->andReturn(
                        $report
                    );
            }
        );

        $exitCode =
            Artisan::call(
                'business:changes'
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exitCode
        );

        $this->assertStringContainsString(
            'MONEY IMP',
            $output
        );

        $this->assertStringContainsString(
            'Business Changes',
            $output
        );

        $this->assertStringContainsString(
            'Compared with: 2026-09-04T12:00:00+00:00',
            $output
        );

        $this->assertStringContainsString(
            'Outstanding invoiced revenue increased from £1,000.00 to £1,500.00.',
            $output
        );

        $this->assertStringContainsString(
            'Financial exposure increased — Outstanding invoiced revenue increased.',
            $output
        );

        $this->assertStringContainsString(
            'Causal analysis and decision guidance are outside this report.',
            $output
        );

        $this->assertStringNotContainsString(
            'Recommended actions:',
            $output
        );

        $this->assertStringNotContainsString(
            "Today's priorities:",
            $output
        );
    }

    public function test_changes_command_does_not_call_missing_baseline_no_change(): void
    {
        $current =
            $this->baseline(
                '2026-09-04 13:00:00',
                1500
            );

        $report =
            new BusinessStateChangeReport(
                current: $current,

                previous: null,

                changes: collect(),

                attention: collect()
            );

        $this->mock(
            BusinessStateChangeReportService::class,
            function (
                MockInterface $mock
            ) use ($report): void {
                $mock
                    ->shouldReceive(
                        'current'
                    )
                    ->once()
                    ->andReturn(
                        $report
                    );
            }
        );

        $exitCode =
            Artisan::call(
                'business:changes'
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exitCode
        );

        $this->assertStringContainsString(
            'No earlier captured business-state baseline',
            $output
        );

        $this->assertStringContainsString(
            'Change comparison is unavailable',
            $output
        );

        $this->assertStringNotContainsString(
            'No changes detected across the captured metric set.',
            $output
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
