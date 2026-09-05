<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class WorkingPatternAssertion extends MoneyImpModel
{
    public const UPDATED_AT = null;

    public const STATUS_CONFIRMED =
        'confirmed';

    public const STATUS_NO_FIXED_PATTERN =
        'no_fixed_pattern';

    public const BASIS_WEEKLY =
        'weekly';

    public const TRUTH_BOUNDARY =
        'A working-pattern assertion is explicit human-confirmed recurring schedule truth for an existing User. A confirmed fixed pattern explicitly records scheduled minutes for every weekday; zero minutes on a day means an explicit recurring non-working day, not unknown. No-fixed-pattern means no fixed recurring weekly distribution is asserted; it does not mean zero contracted capacity, zero availability, inactivity or no employment. Absence of a current assertion means working pattern is not established. Working pattern does not establish contracted capacity, leave, absence, available capacity, utilisation, allocation, billability, recoverability, cost, margin, performance or priority.';

    protected function casts(): array
    {
        return [
            'monday_minutes' => 'integer',

            'tuesday_minutes' => 'integer',

            'wednesday_minutes' => 'integer',

            'thursday_minutes' => 'integer',

            'friday_minutes' => 'integer',

            'saturday_minutes' => 'integer',

            'sunday_minutes' => 'integer',

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
                    'Working pattern assertions are immutable. Create a superseding assertion instead.'
                );
            }
        );

        self::deleting(
            function (): void {
                throw new LogicException(
                    'Working pattern assertions are immutable.'
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
            'supersedes_working_pattern_assertion_id'
        );
    }

    public function scheduledMinutesPerWeek(): ?int
    {
        if (
            $this->pattern_status
            !== self::STATUS_CONFIRMED
        ) {
            return null;
        }

        return array_sum(
            $this->weekdayMinutes()
        );
    }

    /**
     * @return array<string, int>
     */
    public function weekdayMinutes(): array
    {
        if (
            $this->pattern_status
            !== self::STATUS_CONFIRMED
        ) {
            return [];
        }

        return [
            'monday' => (int) $this->monday_minutes,

            'tuesday' => (int) $this->tuesday_minutes,

            'wednesday' => (int) $this->wednesday_minutes,

            'thursday' => (int) $this->thursday_minutes,

            'friday' => (int) $this->friday_minutes,

            'saturday' => (int) $this->saturday_minutes,

            'sunday' => (int) $this->sunday_minutes,
        ];
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
                'Working pattern assertion has an invalid effective date range.'
            );
        }

        if (
            $this->pattern_status
            === self::STATUS_CONFIRMED
        ) {
            if (
                $this->pattern_basis
                !== self::BASIS_WEEKLY
            ) {
                throw new LogicException(
                    'Confirmed working pattern requires explicit weekly basis.'
                );
            }

            $minutes =
                [
                    $this->monday_minutes,
                    $this->tuesday_minutes,
                    $this->wednesday_minutes,
                    $this->thursday_minutes,
                    $this->friday_minutes,
                    $this->saturday_minutes,
                    $this->sunday_minutes,
                ];

            foreach (
                $minutes as $value
            ) {
                if (
                    $value === null
                    || (int) $value < 0
                    || (int) $value > 1440
                ) {
                    throw new LogicException(
                        'Confirmed working pattern requires every weekday to contain explicit minutes between zero and 1440.'
                    );
                }
            }

            if (
                array_sum(
                    array_map(
                        fn ($value) => (int) $value,
                        $minutes
                    )
                ) <= 0
            ) {
                throw new LogicException(
                    'Confirmed working pattern requires positive scheduled minutes in the week.'
                );
            }

            return;
        }

        if (
            $this->pattern_status
            === self::STATUS_NO_FIXED_PATTERN
        ) {
            if (
                $this->pattern_basis !== null
                || $this->monday_minutes !== null
                || $this->tuesday_minutes !== null
                || $this->wednesday_minutes !== null
                || $this->thursday_minutes !== null
                || $this->friday_minutes !== null
                || $this->saturday_minutes !== null
                || $this->sunday_minutes !== null
            ) {
                throw new LogicException(
                    'No-fixed-pattern assertion must not carry recurring weekday minutes.'
                );
            }

            return;
        }

        throw new LogicException(
            'Unsupported working-pattern status.'
        );
    }
}
