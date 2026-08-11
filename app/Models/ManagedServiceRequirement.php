<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagedServiceRequirement extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'minimum_count' => 'integer',
            'weight' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            ManagedServiceTemplate::class,
            'managed_service_template_id'
        );
    }
}
