<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'confidence' => 'decimal:2',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AccountingInvoice::class, 'accounting_invoice_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
