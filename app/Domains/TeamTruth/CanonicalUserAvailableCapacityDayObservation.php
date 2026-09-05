<?php

namespace App\Domains\TeamTruth;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CanonicalUserAvailableCapacityDayObservation
{
    /**
     * @param  array<int, string>  $applicableConfirmedExceptionAssertionIds
     */
    public function __construct(
        public string $date,
        public int $scheduledMinutes,
        public int $confirmedNonWorkingMinutes,
        public int $availableMinutes,
        public string $membershipAssertionId,
        public string $contractedCapacityAssertionId,
        public string $workingPatternAssertionId,
        public string $exceptionCoverageAssertionId,
        public array $applicableConfirmedExceptionAssertionIds,
    ) {
        $parsedDate =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $this->date
            );

        if (
            $parsedDate === false
            || $parsedDate->format(
                'Y-m-d'
            ) !== $this->date
        ) {
            throw new InvalidArgumentException(
                'Available-capacity day observation requires an exact YYYY-MM-DD date.'
            );
        }

        if (
            $this->scheduledMinutes < 0
            || $this->confirmedNonWorkingMinutes < 0
            || $this->availableMinutes < 0
        ) {
            throw new InvalidArgumentException(
                'Available-capacity day minutes cannot be negative.'
            );
        }

        if (
            $this->confirmedNonWorkingMinutes
            > $this->scheduledMinutes
        ) {
            throw new InvalidArgumentException(
                'Confirmed non-working minutes cannot exceed scheduled minutes.'
            );
        }

        if (
            $this->availableMinutes
            !== $this->scheduledMinutes
                - $this->confirmedNonWorkingMinutes
        ) {
            throw new InvalidArgumentException(
                'Available minutes must equal scheduled minutes less confirmed non-working minutes.'
            );
        }

        foreach (
            [
                'membershipAssertionId' => $this->membershipAssertionId,

                'contractedCapacityAssertionId' => $this->contractedCapacityAssertionId,

                'workingPatternAssertionId' => $this->workingPatternAssertionId,

                'exceptionCoverageAssertionId' => $this->exceptionCoverageAssertionId,
            ] as $field => $value
        ) {
            if (
                trim(
                    $value
                ) === ''
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Available-capacity day observation requires %s.',
                        $field
                    )
                );
            }
        }

        $seen =
            [];

        foreach (
            $this->applicableConfirmedExceptionAssertionIds as $assertionId
        ) {
            if (
                ! is_string(
                    $assertionId
                )
                || trim(
                    $assertionId
                ) === ''
            ) {
                throw new InvalidArgumentException(
                    'Applicable confirmed exception assertion ids must be non-empty strings.'
                );
            }

            if (
                isset(
                    $seen[$assertionId]
                )
            ) {
                throw new InvalidArgumentException(
                    'Applicable confirmed exception assertion ids must be unique.'
                );
            }

            $seen[$assertionId] =
                true;
        }
    }
}
