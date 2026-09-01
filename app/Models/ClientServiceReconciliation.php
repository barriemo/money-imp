<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientServiceReconciliation extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'candidate_snapshot' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function clientService(): BelongsTo
    {
        return $this->belongsTo(
            ClientService::class
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
