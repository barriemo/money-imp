<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidence;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidenceSet;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationMissingTruthCatalog;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class BusinessStateExplanationPolicyTest extends TestCase
{
    public function test_outstanding_revenue_movement_is_unestablished_with_specific_missing_truth(): void
    {
        $explanation =
            $this->policy()
                ->assess(
                    $this->contextOnlySet(
                        $this->change(
                            metric: BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,

                            source: 'revenue.outstanding',

                            previousKnown: true,

                            previousValue: 100000,

                            currentKnown: true,

                            currentValue: 120000,

                            kind: BusinessStateChange::INCREASED
                        ),

                        impact: 'Recorded outstanding invoiced revenue is higher than in the captured baseline.'
                    )
                );

        $this->assertSame(
            BusinessStateExplanation::UNESTABLISHED,
            $explanation->status
        );

        $this->assertNull(
            $explanation->interpretation
        );

        $this->assertSame(
            0,
            $explanation->confidence
        );

        $this->assertSame(
            [
                'Invoice-age movement across the compared states is not established.',
                'The contribution of newly issued invoices to the movement is not established.',
                'Payment-timing movement across the compared states is not established.',
                'Credit-note, write-off or accounting-adjustment movement is not established.',
                'Client-level contribution to the movement is not established.',
            ],
            $explanation->missingTruth
                ->all()
        );
    }

    public function test_supported_safe_cash_truth_loss_becomes_established(): void
    {
        $change =
            $this->change(
                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                source: 'financial.cash.safeAvailableCash',

                previousKnown: true,

                previousValue: 5000,

                currentKnown: false,

                currentValue: null,

                kind: BusinessStateChange::BECAME_UNKNOWN
            );

        $explanation =
            $this->policy()
                ->assess(
                    new BusinessStateExplanationEvidenceSet(
                        observation: $change,

                        evidence: collect([
                            $this->context(
                                $change
                            ),

                            $this->support(
                                confidence: 100,

                                source: 'business_state.gap.safe_available_cash_unknown'
                            ),
                        ]),

                        interpretation: 'Safe available cash became unknown because complete current bank and liability evidence is not available.',

                        impact: 'Money Imp can no longer safely state available cash.'
                    )
                );

        $this->assertSame(
            BusinessStateExplanation::ESTABLISHED,
            $explanation->status
        );

        $this->assertSame(
            100,
            $explanation->confidence
        );

        $this->assertTrue(
            $explanation->missingTruth
                ->isEmpty()
        );
    }

    public function test_supported_total_liability_truth_loss_becomes_established(): void
    {
        $change =
            $this->change(
                metric: BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE,

                source: 'financial.liabilities.known',

                previousKnown: true,

                previousValue: 12000,

                currentKnown: false,

                currentValue: null,

                kind: BusinessStateChange::BECAME_UNKNOWN
            );

        $explanation =
            $this->policy()
                ->assess(
                    new BusinessStateExplanationEvidenceSet(
                        observation: $change,

                        evidence: collect([
                            $this->context(
                                $change
                            ),

                            $this->support(
                                confidence: 95,

                                source: 'business_state.gap.liability_coverage_incomplete'
                            ),
                        ]),

                        interpretation: 'Total liability exposure became unknown because liability coverage is incomplete.',

                        impact: 'Known liability exposure remains available, but total liability exposure cannot be established.'
                    )
                );

        $this->assertSame(
            BusinessStateExplanation::ESTABLISHED,
            $explanation->status
        );

        $this->assertSame(
            95,
            $explanation->confidence
        );
    }

    public function test_verified_collectible_truth_loss_remains_unestablished_when_only_context_exists(): void
    {
        $change =
            $this->change(
                metric: BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES,

                source: 'financial.receivables.verifiedCollectible',

                previousKnown: true,

                previousValue: 4000,

                currentKnown: false,

                currentValue: null,

                kind: BusinessStateChange::BECAME_UNKNOWN
            );

        $explanation =
            $this->policy()
                ->assess(
                    $this->contextOnlySet(
                        $change,

                        impact: 'Money Imp can no longer establish verified collectible receivables.'
                    )
                );

        $this->assertSame(
            BusinessStateExplanation::UNESTABLISHED,
            $explanation->status
        );

        $this->assertSame(
            [
                'The evidence change that caused verified collectible receivables to become unestablished is not identified.',
                'The current truth gap establishes that verified collectible receivables are unknown, but does not establish why they became unknown.',
            ],
            $explanation->missingTruth
                ->all()
        );
    }

    public function test_became_known_does_not_invent_reason_for_new_knowability(): void
    {
        $change =
            $this->change(
                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                source: 'financial.cash.safeAvailableCash',

                previousKnown: false,

                previousValue: null,

                currentKnown: true,

                currentValue: 5000,

                kind: BusinessStateChange::BECAME_KNOWN
            );

        $explanation =
            $this->policy()
                ->assess(
                    $this->contextOnlySet(
                        $change,

                        impact: 'The current business state can now establish safe available cash.'
                    )
                );

        $this->assertSame(
            BusinessStateExplanation::UNESTABLISHED,
            $explanation->status
        );

        $this->assertSame(
            0,
            $explanation->confidence
        );

        $this->assertCount(
            2,
            $explanation->missingTruth
        );

        $this->assertStringContainsString(
            'made safe available cash establishable',
            $explanation->missingTruth
                ->first()
        );
    }

    public function test_confidence_is_bounded_by_weakest_support_not_averaged(): void
    {
        $change =
            $this->change(
                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                source: 'financial.cash.safeAvailableCash',

                previousKnown: true,

                previousValue: 5000,

                currentKnown: false,

                currentValue: null,

                kind: BusinessStateChange::BECAME_UNKNOWN
            );

        $explanation =
            $this->policy()
                ->assess(
                    new BusinessStateExplanationEvidenceSet(
                        observation: $change,

                        evidence: collect([
                            $this->context(
                                $change
                            ),

                            $this->support(
                                confidence: 95,

                                source: 'support.high'
                            ),

                            $this->support(
                                confidence: 80,

                                source: 'support.lower'
                            ),
                        ]),

                        interpretation: 'Safe available cash became unknown because current evidence coverage is incomplete.',

                        impact: 'Money Imp can no longer safely state available cash.'
                    )
                );

        $this->assertSame(
            80,
            $explanation->confidence
        );

        $this->assertSame(
            BusinessStateExplanation::ESTABLISHED,
            $explanation->status
        );

        $this->assertNotSame(
            88,
            $explanation->confidence
        );
    }

    public function test_contradictory_evidence_makes_supported_interpretation_partial_without_blended_score(): void
    {
        $change =
            $this->change(
                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                source: 'financial.cash.safeAvailableCash',

                previousKnown: true,

                previousValue: 5000,

                currentKnown: false,

                currentValue: null,

                kind: BusinessStateChange::BECAME_UNKNOWN
            );

        $explanation =
            $this->policy()
                ->assess(
                    new BusinessStateExplanationEvidenceSet(
                        observation: $change,

                        evidence: collect([
                            $this->context(
                                $change
                            ),

                            $this->support(
                                confidence: 90,

                                source: 'support'
                            ),

                            new BusinessStateExplanationEvidence(
                                source: 'contradiction',

                                description: 'Other evidence contradicts the interpretation.',

                                position: BusinessStateExplanationEvidence::CONTRADICTS,

                                confidence: 100
                            ),
                        ]),

                        interpretation: 'Current evidence coverage explains the truth loss.',

                        impact: 'Money Imp can no longer safely state available cash.'
                    )
                );

        $this->assertSame(
            BusinessStateExplanation::PARTIAL,
            $explanation->status
        );

        $this->assertSame(
            90,
            $explanation->confidence
        );

        $this->assertTrue(
            $explanation->missingTruth
                ->isEmpty()
        );

        $this->assertCount(
            1,
            $explanation->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::CONTRADICTS
                )
        );
    }

    public function test_zero_confidence_support_fails_closed(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $change =
            $this->change(
                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                source: 'financial.cash.safeAvailableCash',

                previousKnown: true,

                previousValue: 5000,

                currentKnown: false,

                currentValue: null,

                kind: BusinessStateChange::BECAME_UNKNOWN
            );

        $this->policy()
            ->assess(
                new BusinessStateExplanationEvidenceSet(
                    observation: $change,

                    evidence: collect([
                        $this->context(
                            $change
                        ),

                        $this->support(
                            confidence: 0,

                            source: 'unsupported-confidence'
                        ),
                    ]),

                    interpretation: 'A supposedly supported interpretation.',

                    impact: 'Impact.'
                )
            );
    }

    public function test_unmapped_metric_still_receives_explicit_missing_truth(): void
    {
        $change =
            $this->change(
                metric: 'future_metric',

                source: 'future.source',

                previousKnown: true,

                previousValue: 1,

                currentKnown: true,

                currentValue: 2,

                kind: BusinessStateChange::INCREASED
            );

        $explanation =
            $this->policy()
                ->assess(
                    $this->contextOnlySet(
                        $change,

                        impact: 'Recorded future metric is higher.'
                    )
                );

        $this->assertSame(
            BusinessStateExplanation::UNESTABLISHED,
            $explanation->status
        );

        $this->assertSame(
            [
                'The record-level drivers of the future metric change are not established.',
            ],
            $explanation->missingTruth
                ->all()
        );
    }

    private function policy(): BusinessStateExplanationPolicy
    {
        return new BusinessStateExplanationPolicy(
            missingTruth: new BusinessStateExplanationMissingTruthCatalog
        );
    }

    private function contextOnlySet(
        BusinessStateChange $change,
        string $impact,
    ): BusinessStateExplanationEvidenceSet {
        return new BusinessStateExplanationEvidenceSet(
            observation: $change,

            evidence: collect([
                $this->context(
                    $change
                ),
            ]),

            interpretation: null,

            impact: $impact
        );
    }

    private function context(
        BusinessStateChange $change
    ): BusinessStateExplanationEvidence {
        return new BusinessStateExplanationEvidence(
            source: $change->current->source,

            description: 'The business-state change is established.',

            position: BusinessStateExplanationEvidence::CONTEXT,

            confidence: 100
        );
    }

    private function support(
        int $confidence,
        string $source,
    ): BusinessStateExplanationEvidence {
        return new BusinessStateExplanationEvidence(
            source: $source,

            description: 'Authoritative evidence supports the interpretation.',

            position: BusinessStateExplanationEvidence::SUPPORTS,

            confidence: $confidence
        );
    }

    private function change(
        string $metric,
        string $source,
        bool $previousKnown,
        int|float|null $previousValue,
        bool $currentKnown,
        int|float|null $currentValue,
        string $kind,
    ): BusinessStateChange {
        return new BusinessStateChange(
            previous: new BusinessStateMetric(
                domain: 'test',

                metric: $metric,

                scope: 'business',

                clientId: null,

                client: null,

                source: $source,

                known: $previousKnown,

                value: $previousValue
            ),

            current: new BusinessStateMetric(
                domain: 'test',

                metric: $metric,

                scope: 'business',

                clientId: null,

                client: null,

                source: $source,

                known: $currentKnown,

                value: $currentValue
            ),

            kind: $kind,

            previousAsOf: CarbonImmutable::parse(
                '2026-09-04 12:00:00'
            ),

            currentAsOf: CarbonImmutable::parse(
                '2026-09-04 13:00:00'
            )
        );
    }
}
