<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMemoryTheoryEvidence extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
        ];
    }

    public function theory(): BelongsTo
    {
        return $this->belongsTo(
            BusinessMemoryTheory::class,
            'business_memory_theory_id'
        );
    }

    public function observation(): BelongsTo
    {
        return $this->belongsTo(
            BusinessMemoryObservation::class,
            'business_memory_observation_id'
        );
    }
}
