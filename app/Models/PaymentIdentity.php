<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentIdentity extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'last_matched_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
