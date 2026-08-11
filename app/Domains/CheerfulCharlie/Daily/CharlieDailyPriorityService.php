<?php

namespace App\Domains\CheerfulCharlie\Daily;

use Illuminate\Support\Collection;

class CharlieDailyPriorityService
{
    public function top(
        Collection $findings,
        int $limit = 10
    ): Collection {
        return $findings
            ->sortByDesc(
                'priority_score'
            )
            ->take($limit)
            ->values();
    }
}
