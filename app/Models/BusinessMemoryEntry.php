<?php

namespace App\Models;

use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMemoryEntry extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'entry_type' => BusinessMemoryEntryType::class,

            'occurred_at' => 'datetime',

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
}
