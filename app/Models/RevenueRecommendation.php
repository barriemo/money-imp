<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RevenueRecommendation extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'confidence' => 'integer',
            'estimated_monthly_value' => 'decimal:2',
            'estimated_annual_value' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function supplierAsset(): BelongsTo
    {
        return $this->belongsTo(
            SupplierAsset::class
        );
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(
            RevenueRecommendationEvidence::class
        );
    }
}
