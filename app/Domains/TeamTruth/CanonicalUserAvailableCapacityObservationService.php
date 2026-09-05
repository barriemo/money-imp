<?php

namespace App\Domains\TeamTruth;

use App\Domains\TeamTruth\Services\ContractedCapacityCurrentAssertionService;
use App\Domains\TeamTruth\Services\NonWorkingExceptionCoverageCurrentAssertionService;
use App\Domains\TeamTruth\Services\NonWorkingExceptionCurrentAssertionService;
use App\Domains\TeamTruth\Services\TeamMembershipCurrentAssertionService;
use App\Domains\TeamTruth\Services\WorkingPatternCurrentAssertionService;
use App\Models\ContractedCapacityAssertion;
use App\Models\NonWorkingExceptionAssertion;
use App\Models\NonWorkingExceptionCoverageAssertion;
use App\Models\TeamMembershipAssertion;
use App\Models\User;
use App\Models\WorkingPatternAssertion;
use Carbon\CarbonImmutable;
use LogicException;

final class CanonicalUserAvailableCapacityObservationService
{
    public function __construct(
        private readonly TeamMembershipCurrentAssertionService $membershipAssertions,
        private readonly ContractedCapacityCurrentAssertionService $capacityAssertions,
        private readonly WorkingPatternCurrentAssertionService $workingPatternAssertions,
        private readonly NonWorkingExceptionCoverageCurrentAssertionService $exceptionCoverageAssertions,
        private readonly NonWorkingExceptionCurrentAssertionService $exceptionAssertions,
    ) {}

    public function forUser(
        User $user,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        ?CarbonImmutable $observedAt = null,
    ): CanonicalUserAvailableCapacityObservation {
        $startsOn =
            $startsOn
                ->startOfDay();

        $endsOn =
            $endsOn
                ->startOfDay();

        if (
            $endsOn->lt(
                $startsOn
            )
        ) {
            throw new LogicException(
                'Available-capacity observation requires an end date on or after the start date.'
            );
        }

        $days =
            [];

        $scheduledMinutes =
            0;

        $confirmedNonWorkingMinutes =
            0;

        $availableMinutes =
            0;

        for (
            $date = $startsOn;
            $date->lte(
                $endsOn
            );
            $date = $date->addDay()
        ) {
            $day =
                $this->forUserOnDate(
                    user: $user,
                    date: $date
                );

            $days[] =
                $day;

            $scheduledMinutes +=
                $day->scheduledMinutes;

            $confirmedNonWorkingMinutes +=
                $day->confirmedNonWorkingMinutes;

            $availableMinutes +=
                $day->availableMinutes;
        }

        return new CanonicalUserAvailableCapacityObservation(
            userId: (int) $user->id,

            userName: $user->name,

            startsOn: $startsOn
                ->toDateString(),

            endsOn: $endsOn
                ->toDateString(),

            scheduledMinutes: $scheduledMinutes,

            confirmedNonWorkingMinutes: $confirmedNonWorkingMinutes,

            availableMinutes: $availableMinutes,

            days: $days,

            truthBoundary: CanonicalUserAvailableCapacityObservation::TRUTH_BOUNDARY,

            observedAt: $observedAt
                ?? CarbonImmutable::now()
        );
    }

    private function forUserOnDate(
        User $user,
        CarbonImmutable $date,
    ): CanonicalUserAvailableCapacityDayObservation {
        $dateLabel =
            $date->toDateString();

        $membership =
            $this->membershipAssertions
                ->forUser(
                    user: $user,
                    asOf: $date
                );

        if (
            $membership === null
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: team membership is unknown.',
                    $dateLabel
                )
            );
        }

        if (
            $membership->membership_status
            !== TeamMembershipAssertion::STATUS_MEMBER
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: the User is not a current team member.',
                    $dateLabel
                )
            );
        }

        $capacity =
            $this->capacityAssertions
                ->forUser(
                    user: $user,
                    asOf: $date
                );

        if (
            $capacity === null
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: contracted capacity is unknown.',
                    $dateLabel
                )
            );
        }

        if (
            $capacity->capacity_status
            !== ContractedCapacityAssertion::STATUS_CONFIRMED
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: contracted capacity has no fixed denominator.',
                    $dateLabel
                )
            );
        }

        if (
            $capacity->period_basis
            !== ContractedCapacityAssertion::BASIS_WEEKLY
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: V1 supports only weekly contracted capacity.',
                    $dateLabel
                )
            );
        }

        if (
            $capacity->contracted_minutes === null
            || (int) $capacity->contracted_minutes <= 0
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: confirmed weekly contracted minutes are invalid.',
                    $dateLabel
                )
            );
        }

        $workingPattern =
            $this->workingPatternAssertions
                ->forUser(
                    user: $user,
                    asOf: $date
                );

        if (
            $workingPattern === null
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: working pattern is unknown.',
                    $dateLabel
                )
            );
        }

        if (
            $workingPattern->pattern_status
            !== WorkingPatternAssertion::STATUS_CONFIRMED
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: working pattern has no fixed weekly distribution.',
                    $dateLabel
                )
            );
        }

        if (
            $workingPattern->pattern_basis
            !== WorkingPatternAssertion::BASIS_WEEKLY
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: V1 supports only weekly working patterns.',
                    $dateLabel
                )
            );
        }

        $scheduledMinutesPerWeek =
            $workingPattern
                ->scheduledMinutesPerWeek();

        if (
            $scheduledMinutesPerWeek === null
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: weekly working-pattern minutes are not established.',
                    $dateLabel
                )
            );
        }

        if (
            $scheduledMinutesPerWeek
            !== (int) $capacity->contracted_minutes
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: weekly contracted capacity does not match the weekly working pattern.',
                    $dateLabel
                )
            );
        }

        $coverage =
            $this->exceptionCoverageAssertions
                ->forUser(
                    user: $user,
                    asOf: $date
                );

        if (
            $coverage === null
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: non-working-exception coverage is unknown.',
                    $dateLabel
                )
            );
        }

        if (
            $coverage->coverage_status
            !== NonWorkingExceptionCoverageAssertion::STATUS_COMPLETE
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: non-working-exception coverage is not complete.',
                    $dateLabel
                )
            );
        }

        $coveredFrom =
            CarbonImmutable::instance(
                $coverage->covered_from
            )->startOfDay();

        $coveredTo =
            CarbonImmutable::instance(
                $coverage->covered_to
            )->startOfDay();

        if (
            $date->lt(
                $coveredFrom
            )
            || $date->gt(
                $coveredTo
            )
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: the date is outside complete non-working-exception coverage.',
                    $dateLabel
                )
            );
        }

        $weekday =
            strtolower(
                $date->englishDayOfWeek
            );

        $weekdayMinutes =
            $workingPattern
                ->weekdayMinutes();

        if (
            ! array_key_exists(
                $weekday,
                $weekdayMinutes
            )
            || ! is_int(
                $weekdayMinutes[$weekday]
            )
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: the working pattern does not explicitly represent this weekday.',
                    $dateLabel
                )
            );
        }

        $scheduledMinutes =
            $weekdayMinutes[$weekday];

        if (
            $scheduledMinutes < 0
            || $scheduledMinutes > 1440
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: scheduled day minutes are invalid.',
                    $dateLabel
                )
            );
        }

        $applicableConfirmedExceptions =
            $this->exceptionAssertions
                ->forUser(
                    user: $user,
                    asOf: $date
                )
                ->filter(
                    fn (
                        NonWorkingExceptionAssertion $assertion
                    ): bool => $this->isApplicableConfirmedException(
                        assertion: $assertion,
                        date: $date
                    )
                )
                ->values();

        $fullDayExceptions =
            $applicableConfirmedExceptions
                ->filter(
                    fn (
                        NonWorkingExceptionAssertion $assertion
                    ): bool => $assertion->effect_type
                        === NonWorkingExceptionAssertion::EFFECT_FULL_SCHEDULED_DAY
                )
                ->values();

        $fixedMinuteExceptions =
            $applicableConfirmedExceptions
                ->filter(
                    fn (
                        NonWorkingExceptionAssertion $assertion
                    ): bool => $assertion->effect_type
                        === NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES
                )
                ->values();

        $knownEffectCount =
            $fullDayExceptions->count()
                + $fixedMinuteExceptions->count();

        if (
            $knownEffectCount
            !== $applicableConfirmedExceptions->count()
        ) {
            throw new LogicException(
                sprintf(
                    'Available capacity is not derivable for %s: a confirmed non-working exception has an unsupported effect.',
                    $dateLabel
                )
            );
        }

        if (
            $fullDayExceptions->isNotEmpty()
        ) {
            $confirmedNonWorkingMinutes =
                $scheduledMinutes;
        } else {
            if (
                $fixedMinuteExceptions->count()
                > 1
            ) {
                throw new LogicException(
                    sprintf(
                        'Available capacity is not derivable for %s: multiple fixed-minute exceptions are ambiguous without clock-time overlap truth.',
                        $dateLabel
                    )
                );
            }

            $fixedMinutes =
                $fixedMinuteExceptions
                    ->first()
                    ?->non_working_minutes;

            if (
                $fixedMinutes === null
            ) {
                $confirmedNonWorkingMinutes =
                    0;
            } else {
                $fixedMinutes =
                    (int) $fixedMinutes;

                if (
                    $fixedMinutes
                    > $scheduledMinutes
                ) {
                    throw new LogicException(
                        sprintf(
                            'Available capacity is not derivable for %s: fixed non-working minutes exceed scheduled minutes.',
                            $dateLabel
                        )
                    );
                }

                $confirmedNonWorkingMinutes =
                    $fixedMinutes;
            }
        }

        return new CanonicalUserAvailableCapacityDayObservation(
            date: $dateLabel,

            scheduledMinutes: $scheduledMinutes,

            confirmedNonWorkingMinutes: $confirmedNonWorkingMinutes,

            availableMinutes: $scheduledMinutes
                    - $confirmedNonWorkingMinutes,

            membershipAssertionId: (string) $membership->id,

            contractedCapacityAssertionId: (string) $capacity->id,

            workingPatternAssertionId: (string) $workingPattern->id,

            exceptionCoverageAssertionId: (string) $coverage->id,

            applicableConfirmedExceptionAssertionIds: $applicableConfirmedExceptions
                ->pluck(
                    'id'
                )
                ->map(
                    fn ($id): string => (string) $id
                )
                ->all()
        );
    }

    private function isApplicableConfirmedException(
        NonWorkingExceptionAssertion $assertion,
        CarbonImmutable $date,
    ): bool {
        if (
            $assertion->exception_status
            !== NonWorkingExceptionAssertion::STATUS_CONFIRMED
        ) {
            return false;
        }

        if (
            $assertion->starts_on === null
            || $assertion->ends_on === null
        ) {
            throw new LogicException(
                'A confirmed non-working exception must have an explicit occurrence window.'
            );
        }

        $startsOn =
            CarbonImmutable::instance(
                $assertion->starts_on
            )->startOfDay();

        $endsOn =
            CarbonImmutable::instance(
                $assertion->ends_on
            )->startOfDay();

        return $date->gte(
            $startsOn
        )
            && $date->lte(
                $endsOn
            );
    }
}
