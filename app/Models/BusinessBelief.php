<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BusinessBelief extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'verified' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(
            BusinessBeliefEvidence::class
        );
    }
}
