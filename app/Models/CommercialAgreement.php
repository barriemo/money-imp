<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class CommercialAgreement extends MoneyImpModel
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'contracted_amount_pence' => 'integer',
            'monthly_equivalent' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'renews_on' => 'date',
            'reviewed_at' => 'datetime',
            'terms_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            function (): never {
                throw new LogicException(
                    'Commercial agreement assertions are immutable. Create a superseding assertion instead.'
                );
            }
        );

        static::deleting(
            function (): never {
                throw new LogicException(
                    'Commercial agreement assertions are immutable and cannot be deleted.'
                );
            }
        );
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'supersedes_commercial_agreement_id'
        );
    }

    public function supersededBy(): HasOne
    {
        return $this->hasOne(
            self::class,
            'supersedes_commercial_agreement_id'
        );
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(
            CommercialAgreementEvidence::class
        );
    }
}
