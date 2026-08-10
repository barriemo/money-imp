<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends MoneyImpModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(ProviderAsset::class);
    }
}
