<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagedServiceAsset extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'verified' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            ManagedService::class,
            'managed_service_id'
        );
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(
            SupplierAsset::class,
            'supplier_asset_id'
        );
    }
}
