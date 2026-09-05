<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class NonWorkingExceptionAssertion extends MoneyImpModel
{
    public const UPDATED_AT = null;

    public const STATUS_CONFIRMED =
        'confirmed';

    public const STATUS_CANCELLED =
        'cancelled';

    public const EFFECT_FULL_SCHEDULED_DAY =
        'full_scheduled_day';

    public const EFFECT_FIXED_MINUTES =
        'fixed_minutes';

    public const TRUTH_BOUNDARY =
        'A non-working-exception assertion is explicit human-confirmed capacity-calendar exception truth for an existing User. A confirmed full-scheduled-day effect says otherwise scheduled working minutes in the inclusive occurrence window are explicitly non-working, without deriving those scheduled minutes here. A confirmed fixed-minutes effect records exact non-working minutes on one explicit date. A cancelled assertion removes the exception effect from its own exception chain from its effective date; cancellation does not establish availability. Absence of a current confirmed exception does not establish availability. Multiple independent exceptions may coexist or overlap; this ledger does not aggregate or subtract them. Non-working exception truth does not establish leave entitlement, HR approval, sickness diagnosis, employment, contracted capacity, working pattern, available capacity, utilisation, allocation, billability, recoverability, cost, margin, performance or priority.';

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',

            'ends_on' => 'date',

            'non_working_minutes' => 'integer',

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
                    'Non-working exception assertions are immutable. Create a superseding assertion instead.'
                );
            }
        );

        self::deleting(
            function (): void {
                throw new LogicException(
                    'Non-working exception assertions are immutable.'
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
            'supersedes_non_working_exception_assertion_id'
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
                'Non-working exception assertion has an invalid effective date range.'
            );
        }

        if (
            $this->exception_status
            === self::STATUS_CONFIRMED
        ) {
            if (
                $this->starts_on === null
                || $this->ends_on === null
                || ! in_array(
                    $this->effect_type,
                    [
                        self::EFFECT_FULL_SCHEDULED_DAY,
                        self::EFFECT_FIXED_MINUTES,
                    ],
                    true
                )
            ) {
                throw new LogicException(
                    'Confirmed non-working exception requires an effect and explicit occurrence window.'
                );
            }

            if (
                CarbonImmutable::parse(
                    $this->ends_on
                )->lt(
                    CarbonImmutable::parse(
                        $this->starts_on
                    )
                )
            ) {
                throw new LogicException(
                    'Non-working exception has an invalid occurrence date range.'
                );
            }

            if (
                $this->effect_type
                === self::EFFECT_FULL_SCHEDULED_DAY
            ) {
                if (
                    $this->non_working_minutes
                    !== null
                ) {
                    throw new LogicException(
                        'Full-scheduled-day exception must not carry fixed non-working minutes.'
                    );
                }

                return;
            }

            if (
                CarbonImmutable::parse(
                    $this->starts_on
                )->ne(
                    CarbonImmutable::parse(
                        $this->ends_on
                    )
                )
                || $this->non_working_minutes === null
                || (int) $this->non_working_minutes <= 0
                || (int) $this->non_working_minutes > 1440
            ) {
                throw new LogicException(
                    'Fixed-minutes exception requires one date and positive minutes up to 1440.'
                );
            }

            return;
        }

        if (
            $this->exception_status
            === self::STATUS_CANCELLED
        ) {
            if (
                $this->supersedes_non_working_exception_assertion_id
                === null
            ) {
                throw new LogicException(
                    'Cancelled non-working exception must supersede an existing assertion.'
                );
            }

            if (
                $this->effect_type !== null
                || $this->starts_on !== null
                || $this->ends_on !== null
                || $this->non_working_minutes !== null
            ) {
                throw new LogicException(
                    'Cancelled non-working exception must not carry an active exception effect.'
                );
            }

            return;
        }

        throw new LogicException(
            'Unsupported non-working-exception status.'
        );
    }
}
