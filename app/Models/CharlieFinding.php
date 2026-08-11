<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharlieFinding extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'estimated_monthly_value' => 'float',
            'priority_score' => 'integer',
            'evidence' => 'array',
            'metadata' => 'array',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(
            CharlieReview::class,
            'charlie_review_id'
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }
}
