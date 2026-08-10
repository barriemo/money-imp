<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AccountingBillItem extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(AccountingBill::class, 'accounting_bill_id');
    }

    public function costAllocations(): MorphMany
    {
        return $this->morphMany(CostAllocation::class, 'cost_allocatable');
    }
}
