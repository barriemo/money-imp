<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceSnapshot extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'balance_at' => 'datetime',
            'verified' => 'boolean',
            'confidence' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(
            BankAccount::class
        );
    }
}
