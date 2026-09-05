<?php

namespace App\Domains\WorkIntelligence;

use App\Domains\TeamTruth\CanonicalUserAvailableCapacityObservation;
use App\Domains\TeamTruth\CanonicalUserAvailableCapacityObservationService;
use App\Models\User;
use Carbon\CarbonImmutable;
use LogicException;

final class CanonicalUserRecordedWorkUtilisationObservationService
{
    public function __construct(
        private readonly CanonicalUserWindowedRecordedWorkObservationService $recordedWork,
        private readonly CanonicalUserAvailableCapacityObservationService $availableCapacity,
    ) {}

    public function forUser(
        User $user,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        ?CarbonImmutable $observedAt = null,
    ): CanonicalUserRecordedWorkUtilisationObservation {
        $startsOn =
            $startsOn->startOfDay();

        $endsOn =
            $endsOn->startOfDay();

        if ($endsOn->lt($startsOn)) {
            throw new LogicException(
                'Recorded-work utilisation requires an end date on or after the start date.'
            );
        }

        $observationTime =
            $observedAt
                ?? CarbonImmutable::now();

        $recordedWork =
            $this->recordedWork
                ->forUser(
                    user: $user,
                    startsOn: $startsOn,
                    endsOn: $endsOn,
                    observedAt: $observationTime
                );

        $availableCapacity =
            $this->availableCapacity
                ->forUser(
                    user: $user,
                    startsOn: $startsOn,
                    endsOn: $endsOn,
                    observedAt: $observationTime
                );

        $this->assertExactIdentity(
            requestedUser: $user,
            requestedStartsOn: $startsOn,
            requestedEndsOn: $endsOn,
            recordedWork: $recordedWork,
            availableCapacity: $availableCapacity
        );

        if ($availableCapacity->availableMinutes <= 0) {
            throw new LogicException(
                'Recorded-work utilisation is not derivable when calendar-available minutes are zero.'
            );
        }

        return new CanonicalUserRecordedWorkUtilisationObservation(
            userId: (int) $user->id,

            startsOn: $startsOn->toDateString(),

            endsOn: $endsOn->toDateString(),

            recordedMinutes: $recordedWork->recordedMinutes,

            availableMinutes: $availableCapacity->availableMinutes,

            recordedWorkUtilisationBasisPoints: CanonicalUserRecordedWorkUtilisationObservation::basisPointsFor(
                recordedMinutes: $recordedWork->recordedMinutes,
                availableMinutes: $availableCapacity->availableMinutes
            ),

            truthBoundary: CanonicalUserRecordedWorkUtilisationObservation::TRUTH_BOUNDARY,

            observedAt: $observationTime
        );
    }

    private function assertExactIdentity(
        User $requestedUser,
        CarbonImmutable $requestedStartsOn,
        CarbonImmutable $requestedEndsOn,
        CanonicalUserWindowedRecordedWorkObservation $recordedWork,
        CanonicalUserAvailableCapacityObservation $availableCapacity,
    ): void {
        $userId =
            (int) $requestedUser->id;

        $startsOn =
            $requestedStartsOn->toDateString();

        $endsOn =
            $requestedEndsOn->toDateString();

        if (
            $recordedWork->attributedUserId !== $userId
            || $availableCapacity->userId !== $userId
            || $recordedWork->attributedUserId
                !== $availableCapacity->userId
        ) {
            throw new LogicException(
                'Recorded-work utilisation requires exact User.ID identity across numerator and denominator.'
            );
        }

        if (
            $recordedWork->startsOn !== $startsOn
            || $availableCapacity->startsOn !== $startsOn
            || $recordedWork->startsOn
                !== $availableCapacity->startsOn
        ) {
            throw new LogicException(
                'Recorded-work utilisation requires an exact shared start date.'
            );
        }

        if (
            $recordedWork->endsOn !== $endsOn
            || $availableCapacity->endsOn !== $endsOn
            || $recordedWork->endsOn
                !== $availableCapacity->endsOn
        ) {
            throw new LogicException(
                'Recorded-work utilisation requires an exact shared end date.'
            );
        }
    }
}
