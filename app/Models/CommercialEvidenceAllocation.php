<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialEvidenceAllocation extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'allocated_net_pence' => 'integer',
        ];
    }

    public function allocationSet(): BelongsTo
    {
        return $this->belongsTo(
            CommercialEvidenceAllocationSet::class,
            'allocation_set_id'
        );
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            ClientService::class,
            'client_service_id'
        );
    }
}
