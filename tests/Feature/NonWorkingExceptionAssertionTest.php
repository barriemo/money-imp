<?php

namespace Tests\Feature;

use App\Domains\TeamTruth\Services\NonWorkingExceptionCurrentAssertionService;
use App\Models\NonWorkingExceptionAssertion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class NonWorkingExceptionAssertionTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_exception_contains_exact_user_calendar_truth_with_provenance(): void
    {
        $reviewer =
            User::factory()->create([
                'name' => 'Exception Reviewer',
            ]);

        $subject =
            User::factory()->create();

        $exceptionKey =
            (string) Str::uuid();

        $expected =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                exceptionKey: $exceptionKey,
                overrides: [
                    'starts_on' => '2026-09-14',

                    'ends_on' => '2026-09-18',

                    'source_reference' => 'non-working-review-2026',

                    'reason' => 'Explicitly confirmed non-working window.',
                ]
            );

        $actual =
            app(
                NonWorkingExceptionCurrentAssertionService::class
            )->forException(
                user: $subject,
                exceptionKey: $exceptionKey,
                asOf: CarbonImmutable::parse(
                    '2026-09-05'
                )
            );

        $this->assertNotNull(
            $actual
        );

        $this->assertSame(
            (string) $expected->id,
            (string) $actual->id
        );

        $this->assertSame(
            (int) $subject->id,
            (int) $actual->user_id
        );

        $this->assertSame(
            $exceptionKey,
            (string) $actual->exception_key
        );

        $this->assertSame(
            NonWorkingExceptionAssertion::STATUS_CONFIRMED,
            $actual->exception_status
        );

        $this->assertSame(
            NonWorkingExceptionAssertion::EFFECT_FULL_SCHEDULED_DAY,
            $actual->effect_type
        );

        $this->assertSame(
            '2026-09-14',
            $actual->starts_on->toDateString()
        );

        $this->assertSame(
            '2026-09-18',
            $actual->ends_on->toDateString()
        );

        $this->assertNull(
            $actual->non_working_minutes
        );

        $this->assertSame(
            'non-working-review-2026',
            $actual->source_reference
        );

        $this->assertSame(
            (int) $reviewer->id,
            (int) $actual->reviewed_by
        );

        $this->assertSame(
            'Exception Reviewer',
            $actual->reviewed_by_name
        );
    }

    public function test_user_can_have_multiple_independent_current_exceptions(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $firstKey =
            (string) Str::uuid();

        $secondKey =
            (string) Str::uuid();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            exceptionKey: $firstKey,
            overrides: [
                'starts_on' => '2026-09-10',

                'ends_on' => '2026-09-10',
            ]
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            exceptionKey: $secondKey,
            overrides: [
                'starts_on' => '2026-09-20',

                'ends_on' => '2026-09-21',
            ]
        );

        $current =
            app(
                NonWorkingExceptionCurrentAssertionService::class
            )->forUser(
                user: $subject,
                asOf: CarbonImmutable::parse(
                    '2026-09-05'
                )
            );

        $this->assertCount(
            2,
            $current
        );

        $this->assertEqualsCanonicalizing(
            [
                $firstKey,
                $secondKey,
            ],
            $current
                ->pluck(
                    'exception_key'
                )
                ->map(
                    fn ($value) => (string) $value
                )
                ->all()
        );
    }

    public function test_user_without_exception_has_no_exception_truth_not_availability_truth(): void
    {
        $user =
            User::factory()->create();

        $current =
            app(
                NonWorkingExceptionCurrentAssertionService::class
            )->forUser(
                user: $user,
                asOf: CarbonImmutable::parse(
                    '2026-09-05'
                )
            );

        $this->assertCount(
            0,
            $current
        );

        $this->assertStringContainsString(
            'Absence of a current confirmed exception does not establish availability',
            NonWorkingExceptionAssertion::TRUTH_BOUNDARY
        );
    }

    public function test_full_scheduled_day_exception_may_cover_an_inclusive_date_range(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $assertion =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'starts_on' => '2026-09-14',

                    'ends_on' => '2026-09-18',
                ]
            );

        $this->assertSame(
            NonWorkingExceptionAssertion::EFFECT_FULL_SCHEDULED_DAY,
            $assertion->effect_type
        );

        $this->assertNull(
            $assertion->non_working_minutes
        );
    }

    public function test_fixed_minutes_exception_is_exact_and_single_date(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $assertion =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'effect_type' => NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES,

                    'starts_on' => '2026-09-14',

                    'ends_on' => '2026-09-14',

                    'non_working_minutes' => 180,
                ]
            );

        $this->assertSame(
            180,
            $assertion->non_working_minutes
        );
    }

    public function test_fixed_minutes_exception_cannot_span_multiple_dates(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->expectException(
            LogicException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'effect_type' => NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES,

                'starts_on' => '2026-09-14',

                'ends_on' => '2026-09-15',

                'non_working_minutes' => 180,
            ]
        );
    }

    public function test_fixed_minutes_exception_requires_positive_minutes(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->expectException(
            LogicException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'effect_type' => NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES,

                'non_working_minutes' => 0,
            ]
        );
    }

    public function test_fixed_minutes_exception_cannot_exceed_one_day(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->expectException(
            LogicException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'effect_type' => NonWorkingExceptionAssertion::EFFECT_FIXED_MINUTES,

                'non_working_minutes' => 1441,
            ]
        );
    }

    public function test_full_scheduled_day_exception_must_not_carry_fixed_minutes(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->expectException(
            LogicException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'non_working_minutes' => 180,
            ]
        );
    }

    public function test_invalid_occurrence_range_is_rejected(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->expectException(
            LogicException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'starts_on' => '2026-09-18',

                'ends_on' => '2026-09-14',
            ]
        );
    }

    public function test_invalid_assertion_effective_range_is_rejected(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->expectException(
            LogicException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'effective_from' => '2026-10-01',

                'effective_to' => '2026-09-01',
            ]
        );
    }

    public function test_future_cancellation_does_not_cancel_exception_early(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $exceptionKey =
            (string) Str::uuid();

        $confirmed =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                exceptionKey: $exceptionKey
            );

        $cancelled =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                exceptionKey: $exceptionKey,
                overrides: [
                    'supersedes_non_working_exception_assertion_id' => $confirmed->id,

                    'exception_status' => NonWorkingExceptionAssertion::STATUS_CANCELLED,

                    'effect_type' => null,

                    'starts_on' => null,

                    'ends_on' => null,

                    'non_working_minutes' => null,

                    'effective_from' => '2026-10-01',

                    'reason' => 'Explicit cancellation of the exception.',
                ]
            );

        $service =
            app(
                NonWorkingExceptionCurrentAssertionService::class
            );

        $before =
            $service->forException(
                user: $subject,
                exceptionKey: $exceptionKey,
                asOf: CarbonImmutable::parse(
                    '2026-09-05'
                )
            );

        $after =
            $service->forException(
                user: $subject,
                exceptionKey: $exceptionKey,
                asOf: CarbonImmutable::parse(
                    '2026-10-01'
                )
            );

        $this->assertSame(
            (string) $confirmed->id,
            (string) $before?->id
        );

        $this->assertSame(
            (string) $cancelled->id,
            (string) $after?->id
        );

        $this->assertSame(
            NonWorkingExceptionAssertion::STATUS_CANCELLED,
            $after?->exception_status
        );
    }

    public function test_cancellation_does_not_establish_availability(): void
    {
        $boundary =
            strtolower(
                NonWorkingExceptionAssertion::TRUTH_BOUNDARY
            );

        $this->assertStringContainsString(
            'cancellation does not establish availability',
            $boundary
        );
    }

    public function test_cancelled_exception_requires_predecessor(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->expectException(
            LogicException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'exception_status' => NonWorkingExceptionAssertion::STATUS_CANCELLED,

                'effect_type' => null,

                'starts_on' => null,

                'ends_on' => null,

                'non_working_minutes' => null,
            ]
        );
    }

    public function test_cancelled_exception_must_not_carry_active_effect(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $exceptionKey =
            (string) Str::uuid();

        $confirmed =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                exceptionKey: $exceptionKey
            );

        $this->expectException(
            LogicException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            exceptionKey: $exceptionKey,
            overrides: [
                'supersedes_non_working_exception_assertion_id' => $confirmed->id,

                'exception_status' => NonWorkingExceptionAssertion::STATUS_CANCELLED,

                'starts_on' => '2026-09-20',

                'ends_on' => '2026-09-20',
            ]
        );
    }

    public function test_cross_user_supersession_is_rejected_by_database(): void
    {
        $reviewer =
            User::factory()->create();

        $firstUser =
            User::factory()->create();

        $secondUser =
            User::factory()->create();

        $exceptionKey =
            (string) Str::uuid();

        $first =
            $this->assertion(
                subject: $firstUser,
                reviewer: $reviewer,
                exceptionKey: $exceptionKey
            );

        $this->expectException(
            QueryException::class
        );

        $this->assertion(
            subject: $secondUser,
            reviewer: $reviewer,
            exceptionKey: $exceptionKey,
            overrides: [
                'supersedes_non_working_exception_assertion_id' => $first->id,
            ]
        );
    }

    public function test_cross_exception_supersession_is_rejected_by_database(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $firstKey =
            (string) Str::uuid();

        $secondKey =
            (string) Str::uuid();

        $first =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                exceptionKey: $firstKey
            );

        $this->expectException(
            QueryException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            exceptionKey: $secondKey,
            overrides: [
                'supersedes_non_working_exception_assertion_id' => $first->id,
            ]
        );
    }

    public function test_independent_heads_for_same_exception_key_fail_closed(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $exceptionKey =
            (string) Str::uuid();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            exceptionKey: $exceptionKey
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            exceptionKey: $exceptionKey,
            overrides: [
                'effective_from' => '2026-02-01',
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'does not resolve to exactly one current assertion'
        );

        app(
            NonWorkingExceptionCurrentAssertionService::class
        )->forException(
            user: $subject,
            exceptionKey: $exceptionKey,
            asOf: CarbonImmutable::parse(
                '2026-09-05'
            )
        );
    }

    public function test_one_exception_assertion_cannot_have_two_direct_successors(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $exceptionKey =
            (string) Str::uuid();

        $first =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                exceptionKey: $exceptionKey
            );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            exceptionKey: $exceptionKey,
            overrides: [
                'supersedes_non_working_exception_assertion_id' => $first->id,

                'starts_on' => '2026-09-20',

                'ends_on' => '2026-09-20',

                'effective_from' => '2026-09-10',
            ]
        );

        $this->expectException(
            QueryException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            exceptionKey: $exceptionKey,
            overrides: [
                'supersedes_non_working_exception_assertion_id' => $first->id,

                'starts_on' => '2026-09-21',

                'ends_on' => '2026-09-21',

                'effective_from' => '2026-09-11',
            ]
        );
    }

    public function test_eloquent_update_is_forbidden(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $assertion =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer
            );

        $this->expectException(
            LogicException::class
        );

        $assertion->update([
            'reason' => 'Silent mutation',
        ]);
    }

    public function test_eloquent_delete_is_forbidden(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $assertion =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer
            );

        $this->expectException(
            LogicException::class
        );

        $assertion->delete();
    }

    public function test_raw_database_update_is_forbidden(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $assertion =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer
            );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'non_working_exception_assertions'
        )
            ->where(
                'id',
                $assertion->id
            )
            ->update([
                'reason' => 'Raw mutation',
            ]);
    }

    public function test_raw_database_delete_is_forbidden(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $assertion =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer
            );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'non_working_exception_assertions'
        )
            ->where(
                'id',
                $assertion->id
            )
            ->delete();
    }

    public function test_exception_schema_contains_no_available_capacity_utilisation_or_billability_authority(): void
    {
        $columns =
            Schema::getColumnListing(
                'non_working_exception_assertions'
            );

        $joined =
            strtolower(
                implode(
                    ' ',
                    $columns
                )
            );

        foreach (
            [
                'employment',
                'leave_entitlement',
                'leave_balance',
                'hr_approval',
                'contracted_capacity',
                'working_pattern',
                'availability',
                'available',
                'utilisation',
                'utilization',
                'allocation',
                'billable',
                'billability',
                'recoverable',
                'cost',
                'margin',
                'performance',
                'priority',
                'recommendation',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $joined
            );
        }
    }

    public function test_truth_boundary_keeps_exception_truth_bounded(): void
    {
        $boundary =
            strtolower(
                NonWorkingExceptionAssertion::TRUTH_BOUNDARY
            );

        foreach (
            [
                'explicit human-confirmed capacity-calendar exception truth',
                'inclusive occurrence window',
                'exact non-working minutes on one explicit date',
                'cancellation does not establish availability',
                'absence of a current confirmed exception does not establish availability',
                'multiple independent exceptions may coexist or overlap',
                'does not aggregate or subtract them',
                'does not establish leave entitlement',
                'hr approval',
                'employment',
                'contracted capacity',
                'working pattern',
                'available capacity',
                'utilisation',
                'billability',
                'performance',
            ] as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $boundary
            );
        }
    }

    private function assertion(
        User $subject,
        User $reviewer,
        ?string $exceptionKey = null,
        array $overrides = []
    ): NonWorkingExceptionAssertion {
        $exceptionKey ??=
            (string) Str::uuid();

        return NonWorkingExceptionAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'exception_key' => $exceptionKey,

                        'supersedes_non_working_exception_assertion_id' => null,

                        'exception_status' => NonWorkingExceptionAssertion::STATUS_CONFIRMED,

                        'effect_type' => NonWorkingExceptionAssertion::EFFECT_FULL_SCHEDULED_DAY,

                        'starts_on' => '2026-09-14',

                        'ends_on' => '2026-09-14',

                        'non_working_minutes' => null,

                        'effective_from' => '2026-09-05',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'non-working-exception-review',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 18:20:00',

                        'reason' => 'Explicit human-confirmed non-working exception assertion.',

                        'metadata' => [
                            'scope' => 'non_working_exception',
                        ],
                    ],
                    $overrides
                )
            );
    }
}
