<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialEvidenceAllocationSet extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'source_net_pence' => 'integer',
            'allocated_at' => 'datetime',
            'allocation_snapshot' => 'array',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(
            CompositeCommercialReview::class,
            'composite_commercial_review_id'
        );
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

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'allocated_by'
        );
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(
            CommercialEvidenceAllocation::class,
            'allocation_set_id'
        );
    }
}
