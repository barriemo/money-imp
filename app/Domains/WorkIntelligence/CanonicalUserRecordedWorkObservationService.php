<?php

namespace App\Domains\WorkIntelligence;

use App\Models\User;
use App\Models\WorkLog;
use Carbon\CarbonImmutable;

final class CanonicalUserRecordedWorkObservationService
{
    public function forUser(
        User $user,
        ?CarbonImmutable $asOf = null,
    ): CanonicalUserRecordedWorkObservation {
        $logs =
            WorkLog::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->get();

        $performedDates =
            $logs
                ->map(
                    fn (WorkLog $log): ?string => $log->performed_at
                        ?->toDateString()
                )
                ->filter();

        return new CanonicalUserRecordedWorkObservation(
            attributedUserId: (int) $user->id,

            attributedUserName: $user->name,

            recordedWorkLogCount: $logs->count(),

            recordedMinutes: (int) $logs
                ->sum(
                    'minutes'
                ),

            distinctRecordedClientCount: $logs
                ->pluck(
                    'client_id'
                )
                ->filter()
                ->unique()
                ->count(),

            firstRecordedWorkOn: $performedDates
                ->min(),

            lastRecordedWorkOn: $performedDates
                ->max(),

            truthBoundary: CanonicalUserRecordedWorkObservation::TRUTH_BOUNDARY,

            observedAt: $asOf
                ?? CarbonImmutable::now()
        );
    }
}
