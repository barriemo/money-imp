<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderAsset extends MoneyImpModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'current_cost' => 'decimal:2',
            'started_on' => 'date',
            'renews_on' => 'date',
            'ends_on' => 'date',
            'metadata' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function clientAllocations(): HasMany
    {
        return $this->hasMany(ClientAssetAllocation::class);
    }

    public function costAllocations()
    {
        return $this->morphMany(CostAllocation::class, 'cost_allocatable');
    }
}
