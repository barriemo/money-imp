<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function importRows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }
}
