<?php

namespace App\Domains\WorkIntelligence;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CanonicalUserRecordedWorkObservation
{
    public const TRUTH_BOUNDARY =
        'WorkLog.user_id is recorded attribution to an existing User, not server-verified proof that the attributed user performed the work. At least one authenticated write path accepts a caller-selected existing User id. A User record does not by itself establish employment or team membership. Recorded work-log time does not establish contracted capacity, available capacity, utilisation, allocation, billability, recoverability, performance, cost, margin or priority. No recorded work attributed to a user does not prove inactivity or availability.';

    public function __construct(
        public int $attributedUserId,
        public string $attributedUserName,
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
                'Canonical user recorded-work observation requires a positive user id.'
            );
        }

        if (trim($this->attributedUserName) === '') {
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
