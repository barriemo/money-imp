<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessMemoryTheory extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
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

    public function observations(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessMemoryObservation::class,
            'business_memory_theory_evidence'
        )
            ->withPivot([
                'weight',
                'relationship',
            ])
            ->withTimestamps();
    }
}
