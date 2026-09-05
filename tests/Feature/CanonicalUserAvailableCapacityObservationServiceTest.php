<?php

namespace Tests\Feature;

use App\Domains\TeamTruth\CanonicalUserAvailableCapacityObservation;
use App\Domains\TeamTruth\CanonicalUserAvailableCapacityObservationService;
use App\Models\ContractedCapacityAssertion;
use App\Models\NonWorkingExceptionAssertion;
use App\Models\NonWorkingExceptionCoverageAssertion;
use App\Models\TeamMembershipAssertion;
use App\Models\User;
use App\Models\WorkingPatternAssertion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use ReflectionClass;
use Tests\TestCase;

final class CanonicalUserAvailableCapacityObservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_week_with_complete_truth_derives_calendar_available_minutes(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $sources =
            $this->seedDerivableTruth(
                subject: $subject,
                reviewer: $reviewer
            );

        $observedAt =
            CarbonImmutable::parse(
                '2026-09-05 20:00:00'
            );

        $observation =
            app(
                CanonicalUserAvailableCapacityObservationService::class
            )->forUser(
                user: $subject,
                startsOn: CarbonImmutable::parse(
                    '2026-09-07'
                ),
                endsOn: CarbonImmutable::parse(
                    '2026-09-13'
                ),
                observedAt: $observedAt
            );

        $this->assertSame(
            (int) $subject->id,
            $observation->userId
        );

        $this->assertSame(
            $subject->name,
            $observation->userName
        );

        $this->assertSame(
            '2026-09-07',
            $observation->startsOn
        );

        $this->assertSame(
            '2026-09-13',
            $observation->endsOn
        );

        $this->assertSame(
            2250,
            $observation->scheduledMinutes
        );

        $this->assertSame(
            0,
            $observation->confirmedNonWorkingMinutes
        );

        $this->assertSame(
            2250,
            $observation->availableMinutes
        );

        $this->assertCount(
            7,
            $observation->days
        );

        $this->assertSame(
            450,
            $observation->days[0]->scheduledMinutes
        );

        $this->assertSame(
            0,
            $observation->days[5]->scheduledMinutes
        );

        $this->assertSame(
            (string) $sources['membership']->id,
            $observation->days[0]->membershipAssertionId
        );

        $this->assertSame(
            (string) $sources['capacity']->id,
            $observation->days[0]->contractedCapacityAssertionId
        );

        $this->assertSame(
            (string) $sources['pattern']->id,
            $observation->days[0]->workingPatternAssertionId
        );

        $this->assertSame(
            (string) $sources['coverage']->id,
            $observation->days[0]->exceptionCoverageAssertionId
        );

        $this->assertSame(
            [],
            $observation->days[0]->applicableConfirmedExceptionAssertionIds
        );

        $this->assertSame(
            $observedAt,
            $observation->observedAt
        );

        $this->assertSame(
            CanonicalUserAvailableCapacityObservation::TRUTH_BOUNDARY,
            $observation->truthBoundary
        );
    }

    public function test_complete_coverage_allows_no_exception_to_mean_zero_confirmed_exception_effect_only(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-07',
                endsOn: '2026-09-07'
            );

        $this->assertSame(
            450,
            $observation->scheduledMinutes
        );

        $this->assertSame(
            0,
            $observation->confirmedNonWorkingMinutes
        );

        $this->assertSame(
            450,
            $observation->availableMinutes
        );

        $boundary =
            strtolower(
                $observation->truthBoundary
            );

        $this->assertStringContainsString(
            'complete coverage permits absence of a current confirmed exception',
            $boundary
        );

        $this->assertStringContainsString(
            'zero confirmed non-working-exception effect',
            $boundary
        );
    }

    public function test_full_scheduled_day_exception_removes_that_days_scheduled_minutes(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $exception =
            $this->exceptionAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'starts_on' => '2026-09-09',

                    'ends_on' => '2026-09-09',
                ]
            );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-07',
                endsOn: '2026-09-13'
            );

        $this->assertSame(
            2250,
            $observation->scheduledMinutes
        );

        $this->assertSame(
            450,
            $observation->confirmedNonWorkingMinutes
        );

        $this->assertSame(
            1800,
            $observation->availableMinutes
        );

        $day =
            $observation->days[2];

        $this->assertSame(
            '2026-09-09',
            $day->date
        );

        $this->assertSame(
            450,
            $day->scheduledMinutes
        );

        $this->assertSame(
            450,
            $day->confirmedNonWorkingMinutes
        );

        $this->assertSame(
            0,
            $day->availableMinutes
        );

        $this->assertSame(
            [
                (string) $exception->id,
            ],
            $day->applicableConfirmedExceptionAssertionIds
        );
    }

    public function test_single_fixed_minutes_exception_subtracts_exact_minutes(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $exception =
            $this->exceptionAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'effect_type' => NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES,

                    'starts_on' => '2026-09-10',

                    'ends_on' => '2026-09-10',

                    'non_working_minutes' => 120,
                ]
            );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-10',
                endsOn: '2026-09-10'
            );

        $this->assertSame(
            450,
            $observation->scheduledMinutes
        );

        $this->assertSame(
            120,
            $observation->confirmedNonWorkingMinutes
        );

        $this->assertSame(
            330,
            $observation->availableMinutes
        );

        $this->assertSame(
            [
                (string) $exception->id,
            ],
            $observation
                ->days[0]
                ->applicableConfirmedExceptionAssertionIds
        );
    }

    public function test_full_day_effect_dominates_fixed_minutes_without_double_subtraction(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $full =
            $this->exceptionAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'starts_on' => '2026-09-10',

                    'ends_on' => '2026-09-10',
                ]
            );

        $fixed =
            $this->exceptionAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'effect_type' => NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES,

                    'starts_on' => '2026-09-10',

                    'ends_on' => '2026-09-10',

                    'non_working_minutes' => 120,
                ]
            );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-10',
                endsOn: '2026-09-10'
            );

        $this->assertSame(
            450,
            $observation->scheduledMinutes
        );

        $this->assertSame(
            450,
            $observation->confirmedNonWorkingMinutes
        );

        $this->assertSame(
            0,
            $observation->availableMinutes
        );

        $this->assertEqualsCanonicalizing(
            [
                (string) $full->id,
                (string) $fixed->id,
            ],
            $observation
                ->days[0]
                ->applicableConfirmedExceptionAssertionIds
        );
    }

    public function test_multiple_full_day_effects_have_same_bounded_result(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->exceptionAssertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'starts_on' => '2026-09-10',

                'ends_on' => '2026-09-10',
            ]
        );

        $this->exceptionAssertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'starts_on' => '2026-09-10',

                'ends_on' => '2026-09-10',
            ]
        );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-10',
                endsOn: '2026-09-10'
            );

        $this->assertSame(
            450,
            $observation->confirmedNonWorkingMinutes
        );

        $this->assertSame(
            0,
            $observation->availableMinutes
        );
    }

    public function test_multiple_fixed_minutes_exceptions_without_full_day_fail_closed(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        foreach (
            [
                60,
                120,
            ] as $minutes
        ) {
            $this->exceptionAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'effect_type' => NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES,

                    'starts_on' => '2026-09-10',

                    'ends_on' => '2026-09-10',

                    'non_working_minutes' => $minutes,
                ]
            );
        }

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'multiple fixed-minute exceptions are ambiguous'
        );

        $this->observe(
            subject: $subject,
            startsOn: '2026-09-10',
            endsOn: '2026-09-10'
        );
    }

    public function test_fixed_minutes_exceeding_scheduled_minutes_fail_closed_without_capping(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->exceptionAssertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'effect_type' => NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES,

                'starts_on' => '2026-09-10',

                'ends_on' => '2026-09-10',

                'non_working_minutes' => 500,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'fixed non-working minutes exceed scheduled minutes'
        );

        $this->observe(
            subject: $subject,
            startsOn: '2026-09-10',
            endsOn: '2026-09-10'
        );
    }

    public function test_fixed_minutes_on_explicit_zero_minute_pattern_day_fail_closed(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->exceptionAssertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'effect_type' => NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES,

                'starts_on' => '2026-09-12',

                'ends_on' => '2026-09-12',

                'non_working_minutes' => 60,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'fixed non-working minutes exceed scheduled minutes'
        );

        $this->observe(
            subject: $subject,
            startsOn: '2026-09-12',
            endsOn: '2026-09-12'
        );
    }

    public function test_explicit_zero_minute_pattern_day_can_legitimately_have_zero_available_minutes(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-12',
                endsOn: '2026-09-12'
            );

        $this->assertSame(
            0,
            $observation->scheduledMinutes
        );

        $this->assertSame(
            0,
            $observation->confirmedNonWorkingMinutes
        );

        $this->assertSame(
            0,
            $observation->availableMinutes
        );
    }

    public function test_cancelled_exception_head_contributes_no_effect(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $exceptionKey =
            (string) Str::uuid();

        $confirmed =
            $this->exceptionAssertion(
                subject: $subject,
                reviewer: $reviewer,
                exceptionKey: $exceptionKey,
                overrides: [
                    'starts_on' => '2026-09-08',

                    'ends_on' => '2026-09-09',

                    'effective_from' => '2026-09-01',
                ]
            );

        $this->exceptionAssertion(
            subject: $subject,
            reviewer: $reviewer,
            exceptionKey: $exceptionKey,
            overrides: [
                'supersedes_non_working_exception_assertion_id' => $confirmed->id,

                'exception_status' => NonWorkingExceptionAssertion::STATUS_CANCELLED,

                'effect_type' => null,

                'starts_on' => null,

                'ends_on' => null,

                'non_working_minutes' => null,

                'effective_from' => '2026-09-09',

                'reason' => 'Explicit cancellation.',
            ]
        );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-08',
                endsOn: '2026-09-09'
            );

        $this->assertSame(
            900,
            $observation->scheduledMinutes
        );

        $this->assertSame(
            450,
            $observation->confirmedNonWorkingMinutes
        );

        $this->assertSame(
            450,
            $observation->availableMinutes
        );

        $this->assertCount(
            1,
            $observation
                ->days[0]
                ->applicableConfirmedExceptionAssertionIds
        );

        $this->assertSame(
            [],
            $observation
                ->days[1]
                ->applicableConfirmedExceptionAssertionIds
        );
    }

    public function test_membership_unknown_blocks_derivation_instead_of_becoming_zero(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->capacityAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->workingPatternAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->coverageAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'team membership is unknown'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_not_member_blocks_derivation_instead_of_becoming_zero_available_capacity(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer,
            membershipOverrides: [
                'membership_status' => TeamMembershipAssertion::STATUS_NOT_MEMBER,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'not a current team member'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_contracted_capacity_unknown_blocks_derivation(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->membershipAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->workingPatternAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->coverageAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'contracted capacity is unknown'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_no_fixed_capacity_blocks_derivation_instead_of_becoming_zero(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer,
            capacityOverrides: [
                'capacity_status' => ContractedCapacityAssertion::STATUS_NO_FIXED_CAPACITY,

                'contracted_minutes' => null,

                'period_basis' => null,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'no fixed denominator'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_daily_contracted_capacity_is_not_normalised_to_weekly(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer,
            capacityOverrides: [
                'contracted_minutes' => 450,

                'period_basis' => ContractedCapacityAssertion::BASIS_DAILY,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'supports only weekly contracted capacity'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_monthly_contracted_capacity_is_not_normalised_to_weekly(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer,
            capacityOverrides: [
                'contracted_minutes' => 9750,

                'period_basis' => ContractedCapacityAssertion::BASIS_MONTHLY,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'supports only weekly contracted capacity'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_annual_contracted_capacity_is_not_normalised_to_weekly(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer,
            capacityOverrides: [
                'contracted_minutes' => 117000,

                'period_basis' => ContractedCapacityAssertion::BASIS_ANNUAL,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'supports only weekly contracted capacity'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_working_pattern_unknown_blocks_derivation(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->membershipAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->capacityAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->coverageAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'working pattern is unknown'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_no_fixed_pattern_blocks_derivation_instead_of_becoming_zero(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer,
            patternOverrides: [
                'pattern_status' => WorkingPatternAssertion::STATUS_NO_FIXED_PATTERN,

                'pattern_basis' => null,

                'monday_minutes' => null,

                'tuesday_minutes' => null,

                'wednesday_minutes' => null,

                'thursday_minutes' => null,

                'friday_minutes' => null,

                'saturday_minutes' => null,

                'sunday_minutes' => null,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'no fixed weekly distribution'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_weekly_capacity_and_pattern_total_must_match_exactly(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer,
            capacityOverrides: [
                'contracted_minutes' => 2100,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'does not match the weekly working pattern'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_exception_coverage_unknown_blocks_derivation(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->membershipAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->capacityAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->workingPatternAssertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'non-working-exception coverage is unknown'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_not_complete_exception_coverage_blocks_derivation(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer,
            coverageOverrides: [
                'coverage_status' => NonWorkingExceptionCoverageAssertion::STATUS_NOT_COMPLETE,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'coverage is not complete'
        );

        $this->observe(
            subject: $subject
        );
    }

    public function test_date_outside_complete_exception_coverage_blocks_derivation(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer,
            coverageOverrides: [
                'covered_from' => '2026-09-08',

                'covered_to' => '2026-09-30',
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'outside complete non-working-exception coverage'
        );

        $this->observe(
            subject: $subject,
            startsOn: '2026-09-07',
            endsOn: '2026-09-07'
        );
    }

    public function test_effective_truth_is_resolved_separately_for_each_date(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $membership =
            $this->membershipAssertion(
                subject: $subject,
                reviewer: $reviewer
            );

        $capacity =
            $this->capacityAssertion(
                subject: $subject,
                reviewer: $reviewer
            );

        $pattern =
            $this->workingPatternAssertion(
                subject: $subject,
                reviewer: $reviewer
            );

        $firstCoverage =
            $this->coverageAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'covered_from' => '2026-09-07',

                    'covered_to' => '2026-09-07',

                    'effective_from' => '2026-01-01',
                ]
            );

        $secondCoverage =
            $this->coverageAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'supersedes_non_working_exception_coverage_assertion_id' => $firstCoverage->id,

                    'covered_from' => '2026-09-08',

                    'covered_to' => '2026-09-30',

                    'effective_from' => '2026-09-08',
                ]
            );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-07',
                endsOn: '2026-09-08'
            );

        $this->assertSame(
            900,
            $observation->availableMinutes
        );

        $this->assertSame(
            (string) $membership->id,
            $observation->days[0]->membershipAssertionId
        );

        $this->assertSame(
            (string) $capacity->id,
            $observation->days[0]->contractedCapacityAssertionId
        );

        $this->assertSame(
            (string) $pattern->id,
            $observation->days[0]->workingPatternAssertionId
        );

        $this->assertSame(
            (string) $firstCoverage->id,
            $observation->days[0]->exceptionCoverageAssertionId
        );

        $this->assertSame(
            (string) $secondCoverage->id,
            $observation->days[1]->exceptionCoverageAssertionId
        );
    }

    public function test_invalid_observation_window_fails_closed(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'end date on or after the start date'
        );

        $this->observe(
            subject: $subject,
            startsOn: '2026-09-10',
            endsOn: '2026-09-09'
        );
    }

    public function test_observation_exposes_no_utilisation_billability_allocation_or_performance_authority(): void
    {
        $reflection =
            new ReflectionClass(
                CanonicalUserAvailableCapacityObservation::class
            );

        foreach (
            [
                'utilisation',
                'utilization',
                'utilisationPercentage',
                'utilizationPercentage',
                'recordedWorkMinutes',
                'allocatedMinutes',
                'unallocatedMinutes',
                'freeMinutes',
                'freeCapacity',
                'abilityToAcceptWork',
                'billable',
                'billability',
                'recoverableValue',
                'cost',
                'costRate',
                'margin',
                'marginPercentage',
                'performance',
                'priority',
                'recommendation',
                'recommendedAction',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    public function test_truth_boundary_distinguishes_calendar_availability_from_free_capacity(): void
    {
        $boundary =
            strtolower(
                CanonicalUserAvailableCapacityObservation::TRUTH_BOUNDARY
            );

        foreach (
            [
                'calendar-available scheduled capacity',
                'do not mean unallocated capacity',
                'free capacity',
                'ability to accept work',
                'daily, monthly and annual contracted-capacity bases are not normalised',
                'not_member',
                'no_fixed_capacity',
                'no_fixed_pattern',
                'non-derivable rather than zero',
                'utilisation',
                'allocation',
                'billability',
                'performance',
            ] as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $boundary
            );
        }
    }

    public function test_service_is_read_only_and_does_not_depend_on_work_logs_resources_or_billability(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/TeamTruth/CanonicalUserAvailableCapacityObservationService.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'WorkLog',
                'ResourceIntelligence',
                'ResourceAllocation',
                'ResourceWorkAttribution',
                'BillabilityReasoner',
                'BillabilityAssessment',
                'RecordedWork',
                'costRate',
                'margin',
                'utilisation',
                'utilization',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function observe(
        User $subject,
        string $startsOn = '2026-09-07',
        string $endsOn = '2026-09-07',
    ): CanonicalUserAvailableCapacityObservation {
        return app(
            CanonicalUserAvailableCapacityObservationService::class
        )->forUser(
            user: $subject,
            startsOn: CarbonImmutable::parse(
                $startsOn
            ),
            endsOn: CarbonImmutable::parse(
                $endsOn
            ),
            observedAt: CarbonImmutable::parse(
                '2026-09-05 20:00:00'
            )
        );
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function users(): array
    {
        return [
            User::factory()->create([
                'name' => 'Available Capacity Subject',
            ]),

            User::factory()->create([
                'name' => 'Available Capacity Reviewer',
            ]),
        ];
    }

    /**
     * @return array{
     *     membership: TeamMembershipAssertion,
     *     capacity: ContractedCapacityAssertion,
     *     pattern: WorkingPatternAssertion,
     *     coverage: NonWorkingExceptionCoverageAssertion
     * }
     */
    private function seedDerivableTruth(
        User $subject,
        User $reviewer,
        array $membershipOverrides = [],
        array $capacityOverrides = [],
        array $patternOverrides = [],
        array $coverageOverrides = [],
    ): array {
        return [
            'membership' => $this->membershipAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: $membershipOverrides
            ),

            'capacity' => $this->capacityAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: $capacityOverrides
            ),

            'pattern' => $this->workingPatternAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: $patternOverrides
            ),

            'coverage' => $this->coverageAssertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: $coverageOverrides
            ),
        ];
    }

    private function membershipAssertion(
        User $subject,
        User $reviewer,
        array $overrides = [],
    ): TeamMembershipAssertion {
        return TeamMembershipAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'supersedes_team_membership_assertion_id' => null,

                        'membership_status' => TeamMembershipAssertion::STATUS_MEMBER,

                        'effective_from' => '2026-01-01',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'available-capacity-membership',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 20:00:00',

                        'reason' => 'Explicit membership truth for available-capacity derivation.',

                        'metadata' => [
                            'scope' => 'available_capacity_test',
                        ],
                    ],
                    $overrides
                )
            );
    }

    private function capacityAssertion(
        User $subject,
        User $reviewer,
        array $overrides = [],
    ): ContractedCapacityAssertion {
        return ContractedCapacityAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'supersedes_contracted_capacity_assertion_id' => null,

                        'capacity_status' => ContractedCapacityAssertion::STATUS_CONFIRMED,

                        'contracted_minutes' => 2250,

                        'period_basis' => ContractedCapacityAssertion::BASIS_WEEKLY,

                        'effective_from' => '2026-01-01',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'available-capacity-contract',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 20:00:00',

                        'reason' => 'Explicit contracted-capacity truth for derivation.',

                        'metadata' => [
                            'scope' => 'available_capacity_test',
                        ],
                    ],
                    $overrides
                )
            );
    }

    private function workingPatternAssertion(
        User $subject,
        User $reviewer,
        array $overrides = [],
    ): WorkingPatternAssertion {
        return WorkingPatternAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'supersedes_working_pattern_assertion_id' => null,

                        'pattern_status' => WorkingPatternAssertion::STATUS_CONFIRMED,

                        'pattern_basis' => WorkingPatternAssertion::BASIS_WEEKLY,

                        'monday_minutes' => 450,

                        'tuesday_minutes' => 450,

                        'wednesday_minutes' => 450,

                        'thursday_minutes' => 450,

                        'friday_minutes' => 450,

                        'saturday_minutes' => 0,

                        'sunday_minutes' => 0,

                        'effective_from' => '2026-01-01',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'available-capacity-pattern',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 20:00:00',

                        'reason' => 'Explicit working-pattern truth for available-capacity derivation.',

                        'metadata' => [
                            'scope' => 'available_capacity_test',
                        ],
                    ],
                    $overrides
                )
            );
    }

    private function coverageAssertion(
        User $subject,
        User $reviewer,
        array $overrides = [],
    ): NonWorkingExceptionCoverageAssertion {
        return NonWorkingExceptionCoverageAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'supersedes_non_working_exception_coverage_assertion_id' => null,

                        'coverage_status' => NonWorkingExceptionCoverageAssertion::STATUS_COMPLETE,

                        'covered_from' => '2026-09-01',

                        'covered_to' => '2026-09-30',

                        'effective_from' => '2026-01-01',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'available-capacity-exception-coverage',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 20:00:00',

                        'reason' => 'Explicit complete exception-coverage truth for derivation.',

                        'metadata' => [
                            'scope' => 'available_capacity_test',
                        ],
                    ],
                    $overrides
                )
            );
    }

    private function exceptionAssertion(
        User $subject,
        User $reviewer,
        ?string $exceptionKey = null,
        array $overrides = [],
    ): NonWorkingExceptionAssertion {
        $exceptionKey ??=
            (string) Str::uuid();

        return NonWorkingExceptionAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'exception_key' => $exceptionKey,

                        'supersedes_non_working_exception_assertion_id' => null,

                        'exception_status' => NonWorkingExceptionAssertion::STATUS_CONFIRMED,

                        'effect_type' => NonWorkingExceptionAssertion::EFFECT_FULL_SCHEDULED_DAY,

                        'starts_on' => '2026-09-10',

                        'ends_on' => '2026-09-10',

                        'non_working_minutes' => null,

                        'effective_from' => '2026-01-01',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'available-capacity-exception',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 20:00:00',

                        'reason' => 'Explicit non-working-exception truth for available-capacity derivation.',

                        'metadata' => [
                            'scope' => 'available_capacity_test',
                        ],
                    ],
                    $overrides
                )
            );
    }
}
