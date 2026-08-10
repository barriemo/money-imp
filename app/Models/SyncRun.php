<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncRun extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(
            ExternalConnection::class,
            'external_connection_id'
        );
    }

    public function failures(): HasMany
    {
        return $this->hasMany(SyncFailure::class);
    }
}
