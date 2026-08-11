<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierAsset extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'billable' => 'boolean',
            'active' => 'boolean',
            'observed_cost' => 'decimal:2',
            'expected_charge' => 'decimal:2',
            'first_seen_at' => 'date',
            'last_seen_at' => 'date',
            'renewal_date' => 'date',
            'confidence' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            SupplierProfile::class,
            'supplier_profile_id'
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(
            InfrastructureRelationship::class,
            'from_asset_id'
        );
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(
            InfrastructureRelationship::class,
            'to_asset_id'
        );
    }

    public function billingAllocations(): HasMany
    {
        return $this->hasMany(
            InfrastructureBillingAllocation::class,
            'supplier_asset_id'
        );
    }
}
