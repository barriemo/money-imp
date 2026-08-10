<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAssetAllocation extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'client_charge' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'assigned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ProviderAsset::class, 'provider_asset_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ClientService::class, 'client_service_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
