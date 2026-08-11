<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfrastructureRelationship extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'verified' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function fromAsset(): BelongsTo
    {
        return $this->belongsTo(
            SupplierAsset::class,
            'from_asset_id'
        );
    }

    public function toAsset(): BelongsTo
    {
        return $this->belongsTo(
            SupplierAsset::class,
            'to_asset_id'
        );
    }
}
