<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialAgreement extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'observed_value' => 'decimal:2',
            'monthly_equivalent' => 'decimal:2',
            'confidence' => 'integer',
            'starts_on' => 'date',
            'renews_on' => 'date',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(
            CommercialAgreementEvidence::class
        );
    }
}
