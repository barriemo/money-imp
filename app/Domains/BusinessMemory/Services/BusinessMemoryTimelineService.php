<?php

namespace App\Domains\BusinessMemory\Services;

use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Models\BusinessMemory;
use Illuminate\Support\Collection;

class BusinessMemoryTimelineService
{
    public function timeline(
        BusinessMemory $memory,
        ?BusinessMemoryEntryType $type = null,
        ?int $limit = null
    ): Collection {
        $query = $memory
            ->entries()
            ->orderByDesc(
                'occurred_at'
            )
            ->orderByDesc(
                'created_at'
            );

        if ($type) {
            $query->where(
                'entry_type',
                $type->value
            );
        }

        if ($limit) {
            $query->limit(
                $limit
            );
        }

        return $query->get();
    }
}
