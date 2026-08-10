<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingBill extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccountingBillItem::class);
    }
}
