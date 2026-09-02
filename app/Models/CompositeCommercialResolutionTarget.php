<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompositeCommercialResolutionTarget extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'allocated_net_pence' => 'integer',
            'resolved_at' => 'datetime',
            'resolution_snapshot' => 'array',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(
            CompositeCommercialReview::class,
            'composite_commercial_review_id'
        );
    }

    public function allocationSet(): BelongsTo
    {
        return $this->belongsTo(
            CommercialEvidenceAllocationSet::class,
            'allocation_set_id'
        );
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(
            CommercialEvidenceAllocation::class,
            'commercial_evidence_allocation_id'
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            ClientService::class,
            'client_service_id'
        );
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_by'
        );
    }
}
