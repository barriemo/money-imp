<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends MoneyImpModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(AccountingBill::class);
    }
}
