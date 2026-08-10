<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncFailure extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    public function externalRecord(): BelongsTo
    {
        return $this->belongsTo(ExternalRecord::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
