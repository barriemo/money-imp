<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProfile extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'recoverable' => 'boolean',
            'active' => 'boolean',

            'expected_monthly_cost' => 'decimal:2',

            'expected_annual_cost' => 'decimal:2',

            'last_reviewed_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function defaultClient(): BelongsTo
    {
        return $this->belongsTo(
            Client::class,
            'default_client_id'
        );
    }
}
