<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagedServiceTemplate extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(
            ManagedServiceRequirement::class
        );
    }
}
