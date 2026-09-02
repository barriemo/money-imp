<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompositeCommercialReview extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'candidate_snapshot' => 'array',
        ];
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(
            AccountingInvoiceItem::class,
            'accounting_invoice_item_id'
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }
}
