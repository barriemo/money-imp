<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExternalRecord extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'external_created_at' => 'datetime',
            'external_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'payload' => 'array',
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

    public function recordable(): MorphTo
    {
        return $this->morphTo();
    }
}
