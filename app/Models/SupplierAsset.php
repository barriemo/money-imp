<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
