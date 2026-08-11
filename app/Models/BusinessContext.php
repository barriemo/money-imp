<?php

namespace App\Models;

use App\Domains\BusinessMemory\Enums\BusinessContextType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessContext extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'context_type' => BusinessContextType::class,

            'confidence' => 'integer',

            'verified' => 'boolean',

            'effective_from' => 'datetime',

            'effective_until' => 'datetime',

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
