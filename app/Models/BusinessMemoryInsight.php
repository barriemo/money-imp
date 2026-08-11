<?php

namespace App\Models;

use App\Domains\BusinessMemory\Enums\BusinessMemoryInsightType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMemoryInsight extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'insight_type' => BusinessMemoryInsightType::class,

            'confidence' => 'integer',

            'priority' => 'integer',

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
