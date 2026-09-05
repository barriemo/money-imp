<?php

namespace App\Domains\TeamTruth;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CanonicalUserAvailableCapacityObservation
{
    public const TRUTH_BOUNDARY =
        'A canonical user available-capacity observation is a bounded derived calendar-capacity fact for one exact existing User and one explicit inclusive date window. Derivation requires current MEMBER team-membership truth, current CONFIRMED WEEKLY contracted-capacity truth, current CONFIRMED WEEKLY working-pattern truth whose weekly scheduled-minute total exactly equals the contracted weekly minutes, and current COMPLETE non-working-exception coverage containing every observed date. Only current CONFIRMED non-working exceptions applicable to a date may reduce that date. COMPLETE coverage permits absence of a current confirmed exception inside its covered window to mean zero confirmed non-working-exception effect for that date. A full-scheduled-day exception removes the otherwise scheduled minutes for that date. One fixed-minutes exception may remove its exact minutes when those minutes do not exceed scheduled minutes. Multiple fixed-minutes exceptions on one date without a full-day effect are ambiguous because clock-time overlap is unknown and must fail closed. Daily, monthly and annual contracted-capacity bases are not normalised to weekly in V1. Unknown, NOT_MEMBER, NO_FIXED_CAPACITY, NO_FIXED_PATTERN, incomplete or out-of-window exception coverage, capacity-pattern mismatch, ambiguous exception effects, or exception minutes exceeding scheduled minutes make available capacity non-derivable rather than zero. Available minutes here mean calendar-available scheduled capacity after safely resolved confirmed non-working exceptions; they do not mean unallocated capacity, free capacity, ability to accept work, employment, verified performer identity, utilisation, allocation, billability, recoverability, cost, margin, performance, priority or recommendation.';

    /**
     * @param  array<int, CanonicalUserAvailableCapacityDayObservation>  $days
     */
    public function __construct(
        public int $userId,
        public string $userName,
        public string $startsOn,
        public string $endsOn,
        public int $scheduledMinutes,
        public int $confirmedNonWorkingMinutes,
        public int $availableMinutes,
        public array $days,
        public string $truthBoundary,
        public CarbonImmutable $observedAt,
    ) {
        if (
            $this->userId <= 0
        ) {
            throw new InvalidArgumentException(
                'Available-capacity observation requires a positive user id.'
            );
        }

        if (
            trim(
                $this->userName
            ) === ''
        ) {
            throw new InvalidArgumentException(
                'Available-capacity observation requires a user name.'
            );
        }

        $startsOn =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $this->startsOn
            );

        $endsOn =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $this->endsOn
            );

        if (
            $startsOn === false
            || $startsOn->format(
                'Y-m-d'
            ) !== $this->startsOn
            || $endsOn === false
            || $endsOn->format(
                'Y-m-d'
            ) !== $this->endsOn
            || $endsOn->lt(
                $startsOn
            )
        ) {
            throw new InvalidArgumentException(
                'Available-capacity observation requires a valid inclusive date window.'
            );
        }

        if (
            $this->days === []
        ) {
            throw new InvalidArgumentException(
                'Available-capacity observation requires at least one observed date.'
            );
        }

        $expectedDate =
            $startsOn;

        $scheduledMinutes =
            0;

        $confirmedNonWorkingMinutes =
            0;

        $availableMinutes =
            0;

        foreach (
            $this->days as $day
        ) {
            if (
                ! $day
                    instanceof CanonicalUserAvailableCapacityDayObservation
            ) {
                throw new InvalidArgumentException(
                    'Available-capacity observation days must be canonical day observations.'
                );
            }

            if (
                $day->date
                !== $expectedDate->toDateString()
            ) {
                throw new InvalidArgumentException(
                    'Available-capacity observation days must cover the exact inclusive window in date order.'
                );
            }

            $scheduledMinutes +=
                $day->scheduledMinutes;

            $confirmedNonWorkingMinutes +=
                $day->confirmedNonWorkingMinutes;

            $availableMinutes +=
                $day->availableMinutes;

            $expectedDate =
                $expectedDate->addDay();
        }

        if (
            ! $expectedDate->isSameDay(
                $endsOn->addDay()
            )
        ) {
            throw new InvalidArgumentException(
                'Available-capacity observation days must cover the complete inclusive window.'
            );
        }

        if (
            $this->scheduledMinutes
            !== $scheduledMinutes
            || $this->confirmedNonWorkingMinutes
                !== $confirmedNonWorkingMinutes
            || $this->availableMinutes
                !== $availableMinutes
        ) {
            throw new InvalidArgumentException(
                'Available-capacity observation totals must equal the per-date observations.'
            );
        }

        if (
            $this->availableMinutes
            !== $this->scheduledMinutes
                - $this->confirmedNonWorkingMinutes
        ) {
            throw new InvalidArgumentException(
                'Available-capacity observation total must equal scheduled minutes less confirmed non-working minutes.'
            );
        }

        if (
            trim(
                $this->truthBoundary
            ) === ''
        ) {
            throw new InvalidArgumentException(
                'Available-capacity observation requires an explicit truth boundary.'
            );
        }
    }
}
