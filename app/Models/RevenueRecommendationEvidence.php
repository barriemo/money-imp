<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueRecommendationEvidence extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(
            RevenueRecommendation::class,
            'revenue_recommendation_id'
        );
    }
}
