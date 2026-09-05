<?php

namespace App\Domains\WorkIntelligence;

use App\Models\User;
use App\Models\WorkLog;
use Carbon\CarbonImmutable;
use LogicException;

final class CanonicalUserWindowedRecordedWorkObservationService
{
    public function forUser(
        User $user,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        ?CarbonImmutable $observedAt = null,
    ): CanonicalUserWindowedRecordedWorkObservation {
        $startsOn =
            $startsOn->startOfDay();

        $endsOn =
            $endsOn->startOfDay();

        if ($endsOn->lt($startsOn)) {
            throw new LogicException(
                'Windowed recorded-work observation requires an end date on or after the start date.'
            );
        }

        $logs =
            WorkLog::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNotNull(
                    'performed_at'
                )
                ->whereDate(
                    'performed_at',
                    '>=',
                    $startsOn->toDateString()
                )
                ->whereDate(
                    'performed_at',
                    '<=',
                    $endsOn->toDateString()
                )
                ->get();

        $performedDates =
            $logs
                ->map(
                    fn (WorkLog $log): ?string => $log->performed_at
                        ?->toDateString()
                )
                ->filter();

        return new CanonicalUserWindowedRecordedWorkObservation(
            attributedUserId: (int) $user->id,

            attributedUserName: $user->name,

            startsOn: $startsOn->toDateString(),

            endsOn: $endsOn->toDateString(),

            recordedWorkLogCount: $logs->count(),

            recordedMinutes: (int) $logs->sum(
                'minutes'
            ),

            distinctRecordedClientCount: $logs
                ->pluck(
                    'client_id'
                )
                ->filter()
                ->unique()
                ->count(),

            firstRecordedWorkOn: $performedDates->min(),

            lastRecordedWorkOn: $performedDates->max(),

            truthBoundary: CanonicalUserWindowedRecordedWorkObservation::TRUTH_BOUNDARY,

            observedAt: $observedAt
                    ?? CarbonImmutable::now()
        );
    }
}
