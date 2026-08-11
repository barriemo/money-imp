<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagedServiceComponentKnowledge extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',

            'verified' => 'boolean',

            'metadata' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            ManagedService::class,
            'managed_service_id'
        );
    }
}
