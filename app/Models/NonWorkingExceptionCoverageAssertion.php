<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class NonWorkingExceptionCoverageAssertion extends MoneyImpModel
{
    public const UPDATED_AT = null;

    public const STATUS_COMPLETE =
        'complete';

    public const STATUS_NOT_COMPLETE =
        'not_complete';

    public const TRUTH_BOUNDARY =
        'A non-working-exception coverage assertion is explicit human- or source-confirmed completeness truth for one existing User and one explicit inclusive covered window. COMPLETE means the non-working-exception ledger has been explicitly reviewed as complete for that User and covered window as of the assertion current truth state; only inside that window may absence of a current confirmed non-working exception be interpreted as zero confirmed non-working-exception effect. NOT_COMPLETE means that inference is forbidden. Absence of a current coverage assertion means coverage is unknown and that inference is forbidden. V1 resolves one contiguous current coverage window per User and does not union independent coverage assertions. Coverage completeness does not establish that no leave or absence exists in reality, and does not establish team membership, employment, verified work authorship, contracted capacity, working pattern, scheduled minutes, available capacity, utilisation, allocation, billability, recoverability, cost, margin, performance or priority.';

    protected function casts(): array
    {
        return [
            'covered_from' => 'date',

            'covered_to' => 'date',

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
                    'Non-working exception coverage assertions are immutable. Create a superseding assertion instead.'
                );
            }
        );

        self::deleting(
            function (): void {
                throw new LogicException(
                    'Non-working exception coverage assertions are immutable.'
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
            'supersedes_non_working_exception_coverage_assertion_id'
        );
    }

    private function assertValidPayload(): void
    {
        if (
            ! in_array(
                $this->coverage_status,
                [
                    self::STATUS_COMPLETE,
                    self::STATUS_NOT_COMPLETE,
                ],
                true
            )
        ) {
            throw new LogicException(
                'Unsupported non-working-exception coverage status.'
            );
        }

        if (
            $this->covered_from === null
            || $this->covered_to === null
        ) {
            throw new LogicException(
                'Non-working exception coverage requires an explicit covered window.'
            );
        }

        if (
            CarbonImmutable::parse(
                $this->covered_to
            )->lt(
                CarbonImmutable::parse(
                    $this->covered_from
                )
            )
        ) {
            throw new LogicException(
                'Non-working exception coverage assertion has an invalid covered date range.'
            );
        }

        if (
            $this->effective_from === null
        ) {
            throw new LogicException(
                'Non-working exception coverage requires an effective-from date.'
            );
        }

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
                'Non-working exception coverage assertion has an invalid effective date range.'
            );
        }
    }
}
