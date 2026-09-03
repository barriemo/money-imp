<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class CommercialAgreementCoverageReview extends MoneyImpModel
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'reviewed_at' => 'datetime',
            'evidence_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            function (): never {
                throw new LogicException(
                    'Commercial agreement coverage reviews are immutable. Create a superseding review instead.'
                );
            }
        );

        static::deleting(
            function (): never {
                throw new LogicException(
                    'Commercial agreement coverage reviews are immutable and cannot be deleted.'
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

    public function commercialAgreement(): BelongsTo
    {
        return $this->belongsTo(
            CommercialAgreement::class
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
            'supersedes_commercial_agreement_coverage_review_id'
        );
    }

    public function supersededBy(): HasOne
    {
        return $this->hasOne(
            self::class,
            'supersedes_commercial_agreement_coverage_review_id'
        );
    }
}
