<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfrastructureBillingAllocation extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'confidence' => 'integer',
            'verified' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(
            SupplierAsset::class,
            'supplier_asset_id'
        );
    }
}
