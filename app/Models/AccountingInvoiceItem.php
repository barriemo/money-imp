<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingInvoiceItem extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AccountingInvoice::class, 'accounting_invoice_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ClientService::class, 'client_service_id');
    }
}
