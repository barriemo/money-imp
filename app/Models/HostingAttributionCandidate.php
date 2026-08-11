<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostingAttributionCandidate extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'evidence' => 'array',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function supplierAsset(): BelongsTo
    {
        return $this->belongsTo(
            SupplierAsset::class
        );
    }

    public function managedService(): BelongsTo
    {
        return $this->belongsTo(
            ManagedService::class
        );
    }
}
