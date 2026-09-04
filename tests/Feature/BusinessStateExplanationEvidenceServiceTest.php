<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGap;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGaps;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidence;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidenceService;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationEvidenceSet;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class BusinessStateExplanationEvidenceServiceTest extends TestCase
{
    public function test_ordinary_commercial_change_is_context_only_and_does_not_invent_cause(): void
    {
        $change =
            $this->change(
                metric: BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,

                source: 'revenue.outstanding',

                previousKnown: true,

                previousValue: 1000,

                currentKnown: true,

                currentValue: 1500,

                kind: BusinessStateChange::INCREASED
            );

        $set =
            (
                new BusinessStateExplanationEvidenceService
            )->forChange(
                change: $change,

                state: $this->state()
            );

        $this->assertSame(
            $change,
            $set->observation
        );

        $this->assertNull(
            $set->interpretation
        );

        $this->assertSame(
            'Recorded outstanding invoiced revenue is higher than in the captured baseline.',
            $set->impact
        );

        $this->assertCount(
            1,
            $set->evidence
        );

        $context =
            $set->evidence
                ->first();

        $this->assertSame(
            BusinessStateExplanationEvidence::CONTEXT,
            $context->position
        );

        $this->assertSame(
            'revenue.outstanding',
            $context->source
        );

        $this->assertSame(
            100,
            $context->confidence
        );

        $this->assertSame(
            1000,
            $context->metadata['previous_value']
        );

        $this->assertSame(
            1500,
            $context->metadata['current_value']
        );

        $this->assertCount(
            0,
            $set->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::SUPPORTS
                )
        );
    }

    public function test_safe_cash_truth_loss_uses_authoritative_unknown_gap_as_support(): void
    {
        $change =
            $this->becameUnknown(
                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                source: 'financial.cash.safeAvailableCash',

                previousValue: 5000
            );

        $gap =
            $this->gap(
                domain: 'cash',

                type: 'safe_available_cash_unknown',

                title: 'Safe available cash is unknown',

                description: 'Complete current bank and liability evidence is not available, so Money Imp cannot safely state available cash.'
            );

        $set =
            (
                new BusinessStateExplanationEvidenceService
            )->forChange(
                change: $change,

                state: $this->state(
                    unknowns: collect([
                        $gap,
                    ])
                )
            );

        $this->assertSame(
            'Safe available cash became unknown because complete current bank and liability evidence is not available.',
            $set->interpretation
        );

        $this->assertSame(
            'Money Imp can no longer safely state available cash.',
            $set->impact
        );

        $this->assertCount(
            2,
            $set->evidence
        );

        $support =
            $set->evidence
                ->firstWhere(
                    'position',
                    BusinessStateExplanationEvidence::SUPPORTS
                );

        $this->assertNotNull(
            $support
        );

        $this->assertSame(
            'business_state.gap.safe_available_cash_unknown',
            $support->source
        );

        $this->assertSame(
            $gap->description,
            $support->description
        );

        $this->assertSame(
            'safe_available_cash_unknown',
            $support->metadata['type']
        );
    }

    public function test_total_liability_truth_loss_uses_incomplete_coverage_as_support(): void
    {
        $change =
            $this->becameUnknown(
                metric: BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE,

                source: 'financial.liabilities.known',

                previousValue: 12000
            );

        $set =
            (
                new BusinessStateExplanationEvidenceService
            )->forChange(
                change: $change,

                state: $this->state(
                    unknowns: collect([
                        $this->gap(
                            domain: 'liabilities',

                            type: 'liability_coverage_incomplete',

                            title: 'Total liability exposure is not fully known',

                            description: 'Liability coverage is incomplete. Unknown categories: corporation_tax.'
                        ),
                    ])
                )
            );

        $this->assertSame(
            'Total liability exposure became unknown because liability coverage is incomplete.',
            $set->interpretation
        );

        $this->assertSame(
            'Known liability exposure remains available, but total liability exposure cannot be established.',
            $set->impact
        );

        $this->assertSame(
            BusinessStateExplanationEvidence::SUPPORTS,
            $set->evidence
                ->last()
                ->position
        );
    }

    public function test_verified_collectible_truth_loss_gap_remains_context_not_causal_support(): void
    {
        $change =
            $this->becameUnknown(
                metric: BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES,

                source: 'financial.receivables.verifiedCollectible',

                previousValue: 4000
            );

        $set =
            (
                new BusinessStateExplanationEvidenceService
            )->forChange(
                change: $change,

                state: $this->state(
                    unknowns: collect([
                        $this->gap(
                            domain: 'receivables',

                            type: 'verified_collectible_unknown',

                            title: 'Verified collectible receivables are unknown',

                            description: 'Money Imp cannot yet establish the verified collectible value of outstanding receivables.'
                        ),
                    ])
                )
            );

        $this->assertNull(
            $set->interpretation
        );

        $this->assertCount(
            2,
            $set->evidence
        );

        $this->assertCount(
            0,
            $set->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::SUPPORTS
                )
        );

        $this->assertSame(
            [
                BusinessStateExplanationEvidence::CONTEXT,
                BusinessStateExplanationEvidence::CONTEXT,
            ],
            $set->evidence
                ->pluck(
                    'position'
                )
                ->all()
        );
    }

    public function test_missing_expected_truth_gap_fails_closed_to_context_only(): void
    {
        $set =
            (
                new BusinessStateExplanationEvidenceService
            )->forChange(
                change: $this->becameUnknown(
                    metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                    source: 'financial.cash.safeAvailableCash',

                    previousValue: 5000
                ),

                state: $this->state()
            );

        $this->assertNull(
            $set->interpretation
        );

        $this->assertCount(
            1,
            $set->evidence
        );

        $this->assertSame(
            BusinessStateExplanationEvidence::CONTEXT,
            $set->evidence
                ->first()
                ->position
        );
    }

    public function test_unrelated_gap_cannot_be_used_as_support(): void
    {
        $set =
            (
                new BusinessStateExplanationEvidenceService
            )->forChange(
                change: $this->becameUnknown(
                    metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                    source: 'financial.cash.safeAvailableCash',

                    previousValue: 5000
                ),

                state: $this->state(
                    unknowns: collect([
                        $this->gap(
                            domain: 'liabilities',

                            type: 'liability_coverage_incomplete',

                            title: 'Total liability exposure is not fully known',

                            description: 'Liability coverage is incomplete.'
                        ),
                    ])
                )
            );

        $this->assertNull(
            $set->interpretation
        );

        $this->assertCount(
            0,
            $set->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::SUPPORTS
                )
        );
    }

    public function test_evidence_from_different_current_state_timestamp_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        (
            new BusinessStateExplanationEvidenceService
        )->forChange(
            change: $this->change(
                metric: BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,

                source: 'revenue.outstanding',

                previousKnown: true,

                previousValue: 1000,

                currentKnown: true,

                currentValue: 1500,

                kind: BusinessStateChange::INCREASED
            ),

            state: $this->state(
                asOf: '2026-09-04 13:00:01'
            )
        );
    }

    public function test_evidence_set_rejects_interpretation_without_support(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new BusinessStateExplanationEvidenceSet(
            observation: $this->becameUnknown(
                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                source: 'financial.cash.safeAvailableCash',

                previousValue: 5000
            ),

            evidence: collect([
                $this->contextEvidence(),
            ]),

            interpretation: 'A cause was asserted without support.',

            impact: 'Impact.'
        );
    }

    public function test_evidence_set_rejects_positioned_evidence_without_interpretation(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new BusinessStateExplanationEvidenceSet(
            observation: $this->becameUnknown(
                metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                source: 'financial.cash.safeAvailableCash',

                previousValue: 5000
            ),

            evidence: collect([
                $this->contextEvidence(),

                new BusinessStateExplanationEvidence(
                    source: 'support',

                    description: 'Support.',

                    position: BusinessStateExplanationEvidence::SUPPORTS,

                    confidence: 100
                ),
            ]),

            interpretation: null,

            impact: 'Impact.'
        );
    }

    public function test_evidence_set_requires_context_and_typed_evidence(): void
    {
        foreach (
            [
                collect([
                    new BusinessStateExplanationEvidence(
                        source: 'support',

                        description: 'Support.',

                        position: BusinessStateExplanationEvidence::SUPPORTS,

                        confidence: 100
                    ),
                ]),

                collect([
                    'not evidence',
                ]),
            ] as $evidence
        ) {
            try {
                new BusinessStateExplanationEvidenceSet(
                    observation: $this->becameUnknown(
                        metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                        source: 'financial.cash.safeAvailableCash',

                        previousValue: 5000
                    ),

                    evidence: $evidence,

                    interpretation: null,

                    impact: 'Impact.'
                );

                $this->fail(
                    'Invalid explanation evidence set was accepted.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    private function state(
        ?Collection $unknowns = null,
        string $asOf = '2026-09-04 13:00:00',
    ): BusinessState {
        return new BusinessState(
            financial: Mockery::mock(
                FinancialPosition::class
            ),

            revenue: Mockery::mock(
                RevenueTruthSummary::class
            ),

            clients: collect(),

            gaps: new BusinessStateGaps(
                unknowns: $unknowns
                    ?? collect(),

                evidenceGaps: collect()
            ),

            asOf: CarbonImmutable::parse(
                $asOf
            )
        );
    }

    private function gap(
        string $domain,
        string $type,
        string $title,
        string $description,
    ): BusinessStateGap {
        return new BusinessStateGap(
            domain: $domain,

            type: $type,

            scope: 'business',

            clientId: null,

            client: null,

            title: $title,

            description: $description
        );
    }

    private function becameUnknown(
        string $metric,
        string $source,
        int|float $previousValue,
    ): BusinessStateChange {
        return $this->change(
            metric: $metric,

            source: $source,

            previousKnown: true,

            previousValue: $previousValue,

            currentKnown: false,

            currentValue: null,

            kind: BusinessStateChange::BECAME_UNKNOWN
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

    private function contextEvidence(): BusinessStateExplanationEvidence
    {
        return new BusinessStateExplanationEvidence(
            source: 'test',

            description: 'Context.',

            position: BusinessStateExplanationEvidence::CONTEXT,

            confidence: 100
        );
    }
}
