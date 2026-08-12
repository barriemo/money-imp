<?php

namespace App\Domains\TimelineTruth;

use App\Models\TimelineEvent;
use Illuminate\Support\Collection;

class TimelineTruthService
{
    public function recent(
        int $limit = 50
    ): Collection {
        return TimelineEvent::query()
            ->orderByDesc(
                'occurred_at'
            )
            ->limit(
                $limit
            )
            ->get();
    }

    public function forSubject(
        string $subjectType,
        string $subjectId,
        int $limit = 50
    ): Collection {
        return TimelineEvent::query()
            ->where(
                'subject_type',
                $subjectType
            )
            ->where(
                'subject_id',
                $subjectId
            )
            ->orderByDesc(
                'occurred_at'
            )
            ->limit(
                $limit
            )
            ->get();
    }
}
