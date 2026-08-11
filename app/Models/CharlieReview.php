<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharlieReview extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'finding_count' => 'integer',
            'high_priority_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function findings(): HasMany
    {
        return $this->hasMany(
            CharlieFinding::class
        );
    }
}
