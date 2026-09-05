<?php

namespace App\Domains\WorkIntelligence;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CanonicalUserRecordedWorkObservation
{
    public const TRUTH_BOUNDARY =
        'User identity and WorkLog records are recorded system evidence. A User record does not by itself establish employment or team membership. Recorded work-log time does not establish contracted capacity, available capacity, utilisation, allocation, billability, recoverability, performance, cost, margin or priority. No recorded work does not prove inactivity or availability.';

    public function __construct(
        public int $userId,
        public string $userName,
        public int $recordedWorkLogCount,
        public int $recordedMinutes,
        public int $distinctRecordedClientCount,
        public ?string $firstRecordedWorkOn,
        public ?string $lastRecordedWorkOn,
        public string $truthBoundary,
        public CarbonImmutable $observedAt,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException(
                'Canonical user recorded-work observation requires a positive user id.'
            );
        }

        if (trim($this->userName) === '') {
            throw new InvalidArgumentException(
                'Canonical user recorded-work observation requires a user name.'
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
                        'Canonical user recorded-work observation %s cannot be negative.',
                        $field
                    )
                );
            }
        }

        if (trim($this->truthBoundary) === '') {
            throw new InvalidArgumentException(
                'Canonical user recorded-work observation requires an explicit truth boundary.'
            );
        }
    }
}
