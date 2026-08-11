<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagedServiceCostAllocation extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'allocated_monthly_cost' => 'decimal:2',

            'allocation_percent' => 'decimal:4',

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
