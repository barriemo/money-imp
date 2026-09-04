<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttention;
use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttentionPolicy;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BusinessStateChangeAttentionPolicyTest extends TestCase
{
    public function test_policy_selects_only_explicit_attention_semantics(): void
    {
        $changes =
            collect([
                $this->change(
                    BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,
                    BusinessStateChange::DECREASED,
                    5000,
                    4000
                ),

                $this->change(
                    BusinessStateMetricCatalog::KNOWN_NET_POSITION,
                    BusinessStateChange::DECREASED,
                    3000,
                    2000
                ),

                $this->change(
                    BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,
                    BusinessStateChange::INCREASED,
                    1000,
                    1500
                ),

                $this->change(
                    BusinessStateMetricCatalog::PAYMENTS_WAITING_ALLOCATION,
                    BusinessStateChange::INCREASED,
                    100,
                    200
                ),

                $this->change(
                    BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE,
                    BusinessStateChange::INCREASED,
                    1000,
                    1500
                ),

                $this->change(
                    BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_OUTSTANDING_REVENUE,
                    BusinessStateChange::INCREASED,
                    5,
                    6
                ),

                $this->change(
                    BusinessStateMetricCatalog::RECORDED_UNRECOVERED_WORK_VALUE,
                    BusinessStateChange::INCREASED,
                    500,
                    900
                ),

                $this->change(
                    BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE,
                    BusinessStateChange::INCREASED,
                    10,
                    11
                ),

                $this->change(
                    BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS,
                    BusinessStateChange::INCREASED,
                    1,
                    2
                ),

                /*
                 * Explicitly ignored changes.
                 */
                $this->change(
                    BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,
                    BusinessStateChange::INCREASED,
                    4000,
                    5000
                ),

                $this->change(
                    BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,
                    BusinessStateChange::DECREASED,
                    1500,
                    1000
                ),

                $this->change(
                    BusinessStateMetricCatalog::GROSS_INVOICED_REVENUE_REPRESENTED,
                    BusinessStateChange::INCREASED,
                    10000,
                    12000
                ),

                $this->change(
                    BusinessStateMetricCatalog::CLIENT_RECORDS_MARKED_ACTIVE,
                    BusinessStateChange::DECREASED,
                    10,
                    9
                ),

                /*
                 * Duplicate current-condition representation deliberately
                 * does not create a second attention item.
                 */
                $this->change(
                    BusinessStateMetricCatalog::LEDGER_OUTSTANDING_RECEIVABLES,
                    BusinessStateChange::INCREASED,
                    1000,
                    1500
                ),
            ]);

        $attention =
            (
                new BusinessStateChangeAttentionPolicy
            )->assess(
                $changes
            );

        $this->assertSame(
            [
                BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,
                BusinessStateMetricCatalog::KNOWN_NET_POSITION,
                BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,
                BusinessStateMetricCatalog::PAYMENTS_WAITING_ALLOCATION,
                BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE,
                BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_OUTSTANDING_REVENUE,
                BusinessStateMetricCatalog::RECORDED_UNRECOVERED_WORK_VALUE,
                BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE,
                BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS,
            ],
            $attention
                ->map(
                    fn (BusinessStateChangeAttention $item): string => $item->change
                        ->current
                        ->metric
                )
                ->all()
        );
    }

    public function test_loss_of_authoritative_truth_deserves_attention_without_becoming_a_numeric_decrease(): void
    {
        $attention =
            (
                new BusinessStateChangeAttentionPolicy
            )->assess(
                collect([
                    $this->change(
                        BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,
                        BusinessStateChange::BECAME_UNKNOWN,
                        5000,
                        null
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES,
                        BusinessStateChange::BECAME_UNKNOWN,
                        3000,
                        null
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE,
                        BusinessStateChange::BECAME_UNKNOWN,
                        2000,
                        null
                    ),
                ])
            );

        $this->assertCount(
            3,
            $attention
        );

        $this->assertTrue(
            $attention->every(
                fn (BusinessStateChangeAttention $item): bool => $item->type
                    === BusinessStateChangeAttention::TRUTH_LOST
            )
        );

        $this->assertTrue(
            $attention->every(
                fn (BusinessStateChangeAttention $item): bool => $item->change->kind
                    === BusinessStateChange::BECAME_UNKNOWN
            )
        );

        $this->assertTrue(
            $attention->every(
                fn (BusinessStateChangeAttention $item): bool => $item->change
                    ->current
                    ->known === false
                    && $item->change
                        ->current
                        ->value === null
            )
        );
    }

    public function test_became_known_and_opposite_directions_remain_changes_but_are_not_elevated_to_attention(): void
    {
        $attention =
            (
                new BusinessStateChangeAttentionPolicy
            )->assess(
                collect([
                    $this->change(
                        BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,
                        BusinessStateChange::BECAME_KNOWN,
                        null,
                        0
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE,
                        BusinessStateChange::BECAME_KNOWN,
                        null,
                        0
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,
                        BusinessStateChange::DECREASED,
                        2000,
                        1000
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE,
                        BusinessStateChange::DECREASED,
                        10,
                        9
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS,
                        BusinessStateChange::DECREASED,
                        2,
                        1
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::VERIFIED_BANK_ACCOUNT_RECORDS,
                        BusinessStateChange::INCREASED,
                        1,
                        2
                    ),
                ])
            );

        $this->assertCount(
            0,
            $attention
        );
    }

    public function test_evidence_reduction_rules_are_deterministic(): void
    {
        $attention =
            (
                new BusinessStateChangeAttentionPolicy
            )->assess(
                collect([
                    $this->change(
                        BusinessStateMetricCatalog::CLIENT_RECORDS_WITHOUT_WORK_EVIDENCE,
                        BusinessStateChange::INCREASED,
                        4,
                        5
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::STALE_BANK_ACCOUNT_RECORDS,
                        BusinessStateChange::INCREASED,
                        0,
                        1
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::VERIFIED_BANK_ACCOUNT_RECORDS,
                        BusinessStateChange::DECREASED,
                        3,
                        2
                    ),

                    $this->change(
                        BusinessStateMetricCatalog::APPROVED_BANK_BACKED_PAYMENT_EVIDENCE,
                        BusinessStateChange::DECREASED,
                        5000,
                        4000
                    ),
                ])
            );

        $this->assertCount(
            4,
            $attention
        );

        $this->assertTrue(
            $attention->every(
                fn (BusinessStateChangeAttention $item): bool => $item->type
                    === BusinessStateChangeAttention::EVIDENCE_COVERAGE_REDUCED
            )
        );
    }

    public function test_attention_preserves_change_truth_without_priority_or_recommendation_fields(): void
    {
        $change =
            $this->change(
                BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,
                BusinessStateChange::INCREASED,
                1000,
                1500
            );

        $attention =
            (
                new BusinessStateChangeAttentionPolicy
            )->assess(
                collect([
                    $change,
                ])
            )->first();

        $this->assertSame(
            $change,
            $attention->change
        );

        $this->assertSame(
            BusinessStateChangeAttention::FINANCIAL_EXPOSURE_INCREASED,
            $attention->type
        );

        $this->assertSame(
            'Outstanding invoiced revenue increased.',
            $attention->reason
        );

        $this->assertFalse(
            property_exists(
                $attention,
                'priority'
            )
        );

        $this->assertFalse(
            property_exists(
                $attention,
                'recommendation'
            )
        );

        $this->assertFalse(
            property_exists(
                $attention,
                'action'
            )
        );

        $this->assertFalse(
            property_exists(
                $attention,
                'explanation'
            )
        );
    }

    private function change(
        string $metric,
        string $kind,
        int|float|null $previous,
        int|float|null $current,
    ): BusinessStateChange {
        return new BusinessStateChange(
            previous: $this->metric(
                metric: $metric,

                known: $previous !== null,

                value: $previous
            ),

            current: $this->metric(
                metric: $metric,

                known: $current !== null,

                value: $current
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

    private function metric(
        string $metric,
        bool $known,
        int|float|null $value,
    ): BusinessStateMetric {
        return new BusinessStateMetric(
            domain: 'test',

            metric: $metric,

            scope: 'business',

            clientId: null,

            client: null,

            source: 'test.'.$metric,

            known: $known,

            value: $value
        );
    }
}
