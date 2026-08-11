<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends MoneyImpModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'balance_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(
            AccountBalanceSnapshot::class
        );
    }
}
