<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditFacility extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'reported_balance' => 'decimal:2',
            'reported_balance_at' => 'datetime',
            'minimum_payment' => 'decimal:2',
            'payment_due_at' => 'date',
            'verified' => 'boolean',
            'confidence' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function statementEvidence(): HasMany
    {
        return $this->hasMany(
            CreditStatementEvidence::class
        );
    }
}
