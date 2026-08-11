<?php

namespace App\Models;

use App\Domains\BusinessMemory\Enums\BusinessMemoryObservationType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMemoryObservation extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'observation_type' => BusinessMemoryObservationType::class,

            'confidence' => 'integer',

            'verified' => 'boolean',

            'metadata' => 'array',
        ];
    }

    public function memory(): BelongsTo
    {
        return $this->belongsTo(
            BusinessMemory::class,
            'business_memory_id'
        );
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(
            BusinessMemoryEntry::class,
            'business_memory_entry_id'
        );
    }
}
