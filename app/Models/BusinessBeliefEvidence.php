<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BusinessBeliefEvidence extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'confidence' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function belief(): BelongsTo
    {
        return $this->belongsTo(
            BusinessBelief::class,
            'business_belief_id'
        );
    }

    public function evidence(): MorphTo
    {
        return $this->morphTo();
    }
}
