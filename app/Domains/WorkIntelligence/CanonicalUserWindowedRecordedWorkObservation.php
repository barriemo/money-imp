<?php

namespace App\Domains\WorkIntelligence;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CanonicalUserWindowedRecordedWorkObservation
{
    public const TRUTH_BOUNDARY =
        'A canonical user windowed recorded-work observation contains only WorkLog facts recorded against one exact existing User whose performed_at date falls inside one explicit inclusive date window. WorkLog.user_id is recorded attribution to an existing User, not server-verified proof that the attributed user performed the work. The observation does not establish that every minute actually worked in the window was recorded. Zero recorded minutes means only that zero WorkLog minutes are recorded for this exact User inside this exact window; it does not prove that no work occurred, inactivity, availability or free capacity. Windowed recorded work does not by itself establish employment, team membership, contracted capacity, available capacity, utilisation, allocation, billability, recoverability, productivity, performance, cost, margin, priority or recommendation.';

    public function __construct(
        public int $attributedUserId,
        public string $attributedUserName,
        public string $startsOn,
        public string $endsOn,
        public int $recordedWorkLogCount,
        public int $recordedMinutes,
        public int $distinctRecordedClientCount,
        public ?string $firstRecordedWorkOn,
        public ?string $lastRecordedWorkOn,
        public string $truthBoundary,
        public CarbonImmutable $observedAt,
    ) {
        if ($this->attributedUserId <= 0) {
            throw new InvalidArgumentException(
                'Windowed recorded-work observation requires a positive user id.'
            );
        }

        if (trim($this->attributedUserName) === '') {
            throw new InvalidArgumentException(
                'Windowed recorded-work observation requires a user name.'
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
                'Windowed recorded-work observation requires an end date on or after the start date.'
            );
        }

        foreach (
            [
                'recordedWorkLogCount' => $this->recordedWorkLogCount,

                'recordedMinutes' => $this->recordedMinutes,

                'distinctRecordedClientCount' => $this->distinctRecordedClientCount,
            ] as $field => $value
        ) {
            if ($value < 0) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Windowed recorded-work observation %s cannot be negative.',
                        $field
                    )
                );
            }
        }

        if ($this->recordedWorkLogCount === 0) {
            if (
                $this->firstRecordedWorkOn !== null
                || $this->lastRecordedWorkOn !== null
            ) {
                throw new InvalidArgumentException(
                    'A zero-log windowed recorded-work observation cannot carry first or last recorded-work dates.'
                );
            }
        } else {
            if (
                $this->firstRecordedWorkOn === null
                || $this->lastRecordedWorkOn === null
            ) {
                throw new InvalidArgumentException(
                    'A non-empty windowed recorded-work observation requires first and last recorded-work dates.'
                );
            }

            $first =
                $this->exactDate(
                    value: $this->firstRecordedWorkOn,
                    field: 'first recorded-work date'
                );

            $last =
                $this->exactDate(
                    value: $this->lastRecordedWorkOn,
                    field: 'last recorded-work date'
                );

            if (
                $first->lt($startsOn)
                || $last->gt($endsOn)
                || $last->lt($first)
            ) {
                throw new InvalidArgumentException(
                    'Recorded-work dates must remain inside the exact observation window.'
                );
            }
        }

        if (trim($this->truthBoundary) === '') {
            throw new InvalidArgumentException(
                'Windowed recorded-work observation requires an explicit truth boundary.'
            );
        }
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
                    'Windowed recorded-work observation requires an exact YYYY-MM-DD %s.',
                    $field
                )
            );
        }

        return $date;
    }
}
