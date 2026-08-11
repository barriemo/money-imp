<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ManagedService extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'billable' => 'boolean',
            'expected_monthly_revenue' => 'decimal:2',
            'confidence' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(
            SupplierAsset::class,
            'managed_service_assets'
        )
            ->withPivot([
                'role',
                'confidence',
                'verified',
                'source',
                'metadata',
            ])
            ->withTimestamps();
    }
}
