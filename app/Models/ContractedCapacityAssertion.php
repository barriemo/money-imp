<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ContractedCapacityAssertion extends MoneyImpModel
{
    public const UPDATED_AT = null;

    public const STATUS_CONFIRMED =
        'confirmed';

    public const STATUS_NO_FIXED_CAPACITY =
        'no_fixed_capacity';

    public const BASIS_DAILY =
        'daily';

    public const BASIS_WEEKLY =
        'weekly';

    public const BASIS_MONTHLY =
        'monthly';

    public const BASIS_ANNUAL =
        'annual';

    public const TRUTH_BOUNDARY =
        'A contracted-capacity assertion is explicit human-confirmed working-capacity truth for an existing User. Confirmed capacity records exact positive contracted minutes for an explicit period basis. No-fixed-capacity means no fixed contracted working-capacity denominator is asserted; it does not mean zero availability, zero work or no employment. Absence of a current assertion means contracted capacity is not established. Contracted capacity does not establish working pattern, leave, available capacity, utilisation, allocation, billability, recoverability, cost, margin, performance or priority.';

    protected function casts(): array
    {
        return [
            'contracted_minutes' => 'integer',

            'effective_from' => 'date',

            'effective_to' => 'date',

            'reviewed_at' => 'datetime',

            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::creating(
            function (
                self $assertion
            ): void {
                $assertion
                    ->assertValidPayload();
            }
        );

        self::updating(
            function (): void {
                throw new LogicException(
                    'Contracted capacity assertions are immutable. Create a superseding assertion instead.'
                );
            }
        );

        self::deleting(
            function (): void {
                throw new LogicException(
                    'Contracted capacity assertions are immutable.'
                );
            }
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
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
            'supersedes_contracted_capacity_assertion_id'
        );
    }

    private function assertValidPayload(): void
    {
        if (
            $this->effective_to !== null
            && CarbonImmutable::parse(
                $this->effective_to
            )->lt(
                CarbonImmutable::parse(
                    $this->effective_from
                )
            )
        ) {
            throw new LogicException(
                'Contracted capacity assertion has an invalid effective date range.'
            );
        }

        if (
            $this->capacity_status
            === self::STATUS_CONFIRMED
        ) {
            if (
                $this->contracted_minutes === null
                || (int) $this->contracted_minutes <= 0
                || ! in_array(
                    $this->period_basis,
                    [
                        self::BASIS_DAILY,
                        self::BASIS_WEEKLY,
                        self::BASIS_MONTHLY,
                        self::BASIS_ANNUAL,
                    ],
                    true
                )
            ) {
                throw new LogicException(
                    'Confirmed contracted capacity requires positive minutes and an explicit period basis.'
                );
            }

            return;
        }

        if (
            $this->capacity_status
            === self::STATUS_NO_FIXED_CAPACITY
        ) {
            if (
                $this->contracted_minutes !== null
                || $this->period_basis !== null
            ) {
                throw new LogicException(
                    'No-fixed-capacity assertion must not carry contracted minutes or period basis.'
                );
            }

            return;
        }

        throw new LogicException(
            'Unsupported contracted-capacity status.'
        );
    }
}
