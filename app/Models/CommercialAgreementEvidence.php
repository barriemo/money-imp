<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialAgreementEvidence extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
            'observed_value' => 'decimal:2',
            'confidence' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(
            CommercialAgreement::class,
            'commercial_agreement_id'
        );
    }
}
