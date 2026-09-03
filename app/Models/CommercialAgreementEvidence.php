<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CommercialAgreementEvidence extends MoneyImpModel
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
            'observed_value_pence' => 'integer',
            'confidence' => 'integer',
            'verified' => 'boolean',
            'recorded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            function (): never {
                throw new LogicException(
                    'Commercial agreement evidence is immutable. Add new evidence instead.'
                );
            }
        );

        static::deleting(
            function (): never {
                throw new LogicException(
                    'Commercial agreement evidence is immutable and cannot be deleted.'
                );
            }
        );
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(
            CommercialAgreement::class,
            'commercial_agreement_id'
        );
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }
}
