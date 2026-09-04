<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidence;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationPresenter;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationReport;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BusinessStateExplanationPresenterTest extends TestCase
{
    public function test_first_run_truthfully_says_explanation_comparison_is_unavailable(): void
    {
        $output =
            (
                new BusinessStateExplanationPresenter
            )->present(
                new BusinessStateExplanationReport(
                    current: $this->baseline(
                        '2026-09-04 13:00:00',
                        120000
                    ),

                    previous: null,

                    explanations: collect()
                )
            );

        $this->assertStringContainsString(
            'MONEY IMP',
            $output
        );

        $this->assertStringContainsString(
            'Business Explanations',
            $output
        );

        $this->assertStringContainsString(
            'No earlier captured business-state baseline.',
            $output
        );

        $this->assertStringContainsString(
            'Explanation comparison is unavailable',
            $output
        );

        $this->assertStringNotContainsString(
            'No changes detected',
            $output
        );
    }

    public function test_baseline_with_no_changes_says_there_is_nothing_new_to_explain(): void
    {
        $output =
            (
                new BusinessStateExplanationPresenter
            )->present(
                new BusinessStateExplanationReport(
                    current: $this->baseline(
                        '2026-09-04 13:00:00',
                        120000
                    ),

                    previous: $this->baseline(
                        '2026-09-04 12:00:00',
                        120000
                    ),

                    explanations: collect()
                )
            );

        $this->assertStringContainsString(
            'No changes detected across the captured metric set; there is nothing new to explain.',
            $output
        );
    }

    public function test_unestablished_explanation_surfaces_missing_truth_not_invented_why(): void
    {
        $change =
            $this->change(
                previousValue: 100000,

                currentValue: 120000
            );

        $explanation =
            new BusinessStateExplanation(
                observation: $change,

                status: BusinessStateExplanation::UNESTABLISHED,

                evidence: collect([
                    new BusinessStateExplanationEvidence(
                        source: 'revenue.outstanding',

                        description: 'Business-state metric outstanding_invoiced_revenue increased from 100000 to 120000.',

                        position: BusinessStateExplanationEvidence::CONTEXT,

                        confidence: 100
                    ),
                ]),

                interpretation: null,

                impact: 'Recorded outstanding invoiced revenue is higher than in the captured baseline.',

                confidence: 0,

                missingTruth: collect([
                    'Invoice-age movement across the compared states is not established.',
                    'Payment-timing movement across the compared states is not established.',
                ])
            );

        $output =
            (
                new BusinessStateExplanationPresenter
            )->present(
                new BusinessStateExplanationReport(
                    current: $this->baseline(
                        '2026-09-04 13:00:00',
                        120000
                    ),

                    previous: $this->baseline(
                        '2026-09-04 12:00:00',
                        100000
                    ),

                    explanations: collect([
                        $explanation,
                    ])
                )
            );

        $this->assertStringContainsString(
            'Outstanding invoiced revenue — UNESTABLISHED',
            $output
        );

        $this->assertStringContainsString(
            'increased from £100,000.00 to £120,000.00.',
            $output
        );

        $this->assertStringContainsString(
            'Interpretation: Not established from current evidence.',
            $output
        );

        $this->assertStringContainsString(
            'Interpretation confidence: 0%',
            $output
        );

        $this->assertStringContainsString(
            'CONTEXT [100%] revenue.outstanding',
            $output
        );

        $this->assertStringContainsString(
            'Invoice-age movement across the compared states is not established.',
            $output
        );

        $this->assertStringContainsString(
            'Payment-timing movement across the compared states is not established.',
            $output
        );
    }

    public function test_established_explanation_surfaces_support_and_confidence(): void
    {
        $previous =
            new BusinessStateMetric(
                domain: 'financial',

                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                scope: 'business',

                clientId: null,

                client: null,

                source: 'financial.cash.safeAvailableCash',

                known: true,

                value: 5000
            );

        $current =
            new BusinessStateMetric(
                domain: 'financial',

                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                scope: 'business',

                clientId: null,

                client: null,

                source: 'financial.cash.safeAvailableCash',

                known: false,

                value: null
            );

        $change =
            new BusinessStateChange(
                previous: $previous,

                current: $current,

                kind: BusinessStateChange::BECAME_UNKNOWN,

                previousAsOf: CarbonImmutable::parse(
                    '2026-09-04 12:00:00'
                ),

                currentAsOf: CarbonImmutable::parse(
                    '2026-09-04 13:00:00'
                )
            );

        $explanation =
            new BusinessStateExplanation(
                observation: $change,

                status: BusinessStateExplanation::ESTABLISHED,

                evidence: collect([
                    new BusinessStateExplanationEvidence(
                        source: 'financial.cash.safeAvailableCash',

                        description: 'Safe available cash became unknown.',

                        position: BusinessStateExplanationEvidence::CONTEXT,

                        confidence: 100
                    ),

                    new BusinessStateExplanationEvidence(
                        source: 'business_state.gap.safe_available_cash_unknown',

                        description: 'Complete current bank and liability evidence is not available.',

                        position: BusinessStateExplanationEvidence::SUPPORTS,

                        confidence: 100
                    ),
                ]),

                interpretation: 'Safe available cash became unknown because complete current bank and liability evidence is not available.',

                impact: 'Money Imp can no longer safely state available cash.',

                confidence: 100,

                missingTruth: collect()
            );

        $output =
            (
                new BusinessStateExplanationPresenter
            )->present(
                new BusinessStateExplanationReport(
                    current: new BusinessStateBaseline(
                        metrics: collect([
                            $current,
                        ]),

                        asOf: CarbonImmutable::parse(
                            '2026-09-04 13:00:00'
                        )
                    ),

                    previous: new BusinessStateBaseline(
                        metrics: collect([
                            $previous,
                        ]),

                        asOf: CarbonImmutable::parse(
                            '2026-09-04 12:00:00'
                        )
                    ),

                    explanations: collect([
                        $explanation,
                    ])
                )
            );

        $this->assertStringContainsString(
            'Safe available cash — ESTABLISHED',
            $output
        );

        $this->assertStringContainsString(
            'previous established value was £5,000.00.',
            $output
        );

        $this->assertStringContainsString(
            'Interpretation: Safe available cash became unknown because complete current bank and liability evidence is not available.',
            $output
        );

        $this->assertStringContainsString(
            'Interpretation confidence: 100%',
            $output
        );

        $this->assertStringContainsString(
            'SUPPORTS [100%] business_state.gap.safe_available_cash_unknown',
            $output
        );

        $this->assertStringContainsString(
            'Missing truth:'.PHP_EOL.'- None identified for this interpretation.',
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

    private function change(
        int|float $previousValue,
        int|float $currentValue,
    ): BusinessStateChange {
        return new BusinessStateChange(
            previous: $this->baseline(
                '2026-09-04 12:00:00',
                $previousValue
            )
                ->metrics
                ->first(),

            current: $this->baseline(
                '2026-09-04 13:00:00',
                $currentValue
            )
                ->metrics
                ->first(),

            kind: BusinessStateChange::INCREASED,

            previousAsOf: CarbonImmutable::parse(
                '2026-09-04 12:00:00'
            ),

            currentAsOf: CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            )
        );
    }
}
