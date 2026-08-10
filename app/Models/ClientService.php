<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientService extends MoneyImpModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'target_margin_percent' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function billingRules(): HasMany
    {
        return $this->hasMany(BillingRule::class);
    }

    public function assetAllocations(): HasMany
    {
        return $this->hasMany(ClientAssetAllocation::class);
    }

    public function costAllocations(): HasMany
    {
        return $this->hasMany(CostAllocation::class);
    }
}
