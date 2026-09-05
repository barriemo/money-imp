<?php

namespace App\Domains\WorkIntelligence;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use OverflowException;

final readonly class CanonicalUserRecordedWorkUtilisationObservation
{
    public const BASIS_POINTS_PER_HUNDRED_PERCENT = 10_000;

    public const TRUTH_BOUNDARY =
        'Recorded-work utilisation is a bounded derived ratio for one exact User and one exact inclusive date window. Its numerator is WorkLog minutes recorded against that User inside the exact performed_at window; the numerator does not establish that every minute actually worked was recorded and does not verify performer identity. Its denominator is canonical calendar-available scheduled capacity for the same User and exact window; that denominator does not mean free, unallocated, billable or productive capacity. The ratio is represented as integer basis points where 10,000 basis points equals 100.00%, rounded half up to the nearest basis point. Zero recorded minutes against positive calendar-available minutes produces zero recorded-work utilisation only; it does not prove that no work occurred, idleness, free capacity or under-performance. Zero calendar-available minutes makes recorded-work utilisation non-derivable and must never become 0%, 100% or infinity. Recorded-work utilisation may exceed 100% and must not be capped. A value above 100% does not by itself establish over-allocation, over-utilisation, overwork, productivity or performance. Recorded-work utilisation does not establish actual or complete utilisation, complete work capture, attendance, employment, productivity, performance, free capacity, unallocated capacity, allocation, billability, recoverability, cost, margin, priority, recommendation or management action.';

    public function __construct(
        public int $userId,
        public string $startsOn,
        public string $endsOn,
        public int $recordedMinutes,
        public int $availableMinutes,
        public int $recordedWorkUtilisationBasisPoints,
        public string $truthBoundary,
        public CarbonImmutable $observedAt,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException(
                'Recorded-work utilisation requires a positive user id.'
            );
        }

        $startsOn =
            $this->exactDate(
                value: $this->startsOn,
                field: 'start date'
            );

        $endsOn =
            $this->exactDate(
                value: $this->endsOn,
                field: 'end date'
            );

        if ($endsOn->lt($startsOn)) {
            throw new InvalidArgumentException(
                'Recorded-work utilisation requires an end date on or after the start date.'
            );
        }

        if ($this->recordedMinutes < 0) {
            throw new InvalidArgumentException(
                'Recorded-work utilisation recorded minutes cannot be negative.'
            );
        }

        if ($this->availableMinutes <= 0) {
            throw new InvalidArgumentException(
                'Recorded-work utilisation requires positive calendar-available minutes.'
            );
        }

        if ($this->recordedWorkUtilisationBasisPoints < 0) {
            throw new InvalidArgumentException(
                'Recorded-work utilisation basis points cannot be negative.'
            );
        }

        $expectedBasisPoints =
            self::basisPointsFor(
                recordedMinutes: $this->recordedMinutes,
                availableMinutes: $this->availableMinutes
            );

        if (
            $this->recordedWorkUtilisationBasisPoints
            !== $expectedBasisPoints
        ) {
            throw new InvalidArgumentException(
                'Recorded-work utilisation basis points must equal the exact bounded ratio rounded half up to the nearest basis point.'
            );
        }

        if (trim($this->truthBoundary) === '') {
            throw new InvalidArgumentException(
                'Recorded-work utilisation requires an explicit truth boundary.'
            );
        }
    }

    public static function basisPointsFor(
        int $recordedMinutes,
        int $availableMinutes,
    ): int {
        if ($recordedMinutes < 0) {
            throw new InvalidArgumentException(
                'Recorded-work utilisation recorded minutes cannot be negative.'
            );
        }

        if ($availableMinutes <= 0) {
            throw new InvalidArgumentException(
                'Recorded-work utilisation requires positive calendar-available minutes.'
            );
        }

        $whole =
            intdiv(
                $recordedMinutes,
                $availableMinutes
            );

        $remainder =
            $recordedMinutes
                % $availableMinutes;

        if (
            $whole
            > intdiv(
                PHP_INT_MAX,
                self::BASIS_POINTS_PER_HUNDRED_PERCENT
            )
        ) {
            throw new OverflowException(
                'Recorded-work utilisation ratio exceeds supported integer range.'
            );
        }

        if (
            $remainder
            > intdiv(
                PHP_INT_MAX,
                self::BASIS_POINTS_PER_HUNDRED_PERCENT
            )
        ) {
            throw new OverflowException(
                'Recorded-work utilisation remainder exceeds supported integer range.'
            );
        }

        $wholeBasisPoints =
            $whole
                * self::BASIS_POINTS_PER_HUNDRED_PERCENT;

        $scaledRemainder =
            $remainder
                * self::BASIS_POINTS_PER_HUNDRED_PERCENT;

        $halfAvailable =
            intdiv(
                $availableMinutes,
                2
            );

        if (
            $scaledRemainder
            > PHP_INT_MAX - $halfAvailable
        ) {
            throw new OverflowException(
                'Recorded-work utilisation rounding exceeds supported integer range.'
            );
        }

        $fractionBasisPoints =
            intdiv(
                $scaledRemainder
                    + $halfAvailable,
                $availableMinutes
            );

        if (
            $wholeBasisPoints
            > PHP_INT_MAX - $fractionBasisPoints
        ) {
            throw new OverflowException(
                'Recorded-work utilisation basis points exceed supported integer range.'
            );
        }

        return $wholeBasisPoints
            + $fractionBasisPoints;
    }

    private function exactDate(
        string $value,
        string $field,
    ): CarbonImmutable {
        $date =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $value
            );

        if (
            $date === false
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Recorded-work utilisation requires an exact YYYY-MM-DD %s.',
                    $field
                )
            );
        }

        return $date;
    }
}
