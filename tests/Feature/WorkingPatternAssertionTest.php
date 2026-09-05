<?php

namespace Tests\Feature;

use App\Domains\TeamTruth\Services\WorkingPatternCurrentAssertionService;
use App\Models\User;
use App\Models\WorkingPatternAssertion;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class WorkingPatternAssertionTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_assertion_contains_exact_user_weekly_pattern_with_provenance(): void
    {
        $reviewer =
            User::factory()->create([
                'name' => 'Pattern Reviewer',
            ]);

        $subject =
            User::factory()->create();

        $other =
            User::factory()->create();

        $expected =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'monday_minutes' => 450,

                    'tuesday_minutes' => 450,

                    'wednesday_minutes' => 450,

                    'thursday_minutes' => 450,

                    'friday_minutes' => 450,

                    'saturday_minutes' => 0,

                    'sunday_minutes' => 0,

                    'source_reference' => 'working-pattern-review-2026',
                ]
            );

        $this->assertion(
            subject: $other,
            reviewer: $reviewer,
            overrides: [
                'monday_minutes' => 300,
            ]
        );

        $actual =
            app(
                WorkingPatternCurrentAssertionService::class
            )->forUser(
                user: $subject,
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
            WorkingPatternAssertion::STATUS_CONFIRMED,
            $actual->pattern_status
        );

        $this->assertSame(
            WorkingPatternAssertion::BASIS_WEEKLY,
            $actual->pattern_basis
        );

        $this->assertSame(
            2250,
            $actual->scheduledMinutesPerWeek()
        );

        $this->assertSame(
            0,
            $actual->saturday_minutes
        );

        $this->assertSame(
            'working-pattern-review-2026',
            $actual->source_reference
        );

        $this->assertSame(
            (int) $reviewer->id,
            (int) $actual->reviewed_by
        );

        $this->assertSame(
            'Pattern Reviewer',
            $actual->reviewed_by_name
        );
    }

    public function test_user_without_pattern_assertion_remains_unknown(): void
    {
        $user =
            User::factory()->create();

        $actual =
            app(
                WorkingPatternCurrentAssertionService::class
            )->forUser(
                user: $user,
                asOf: CarbonImmutable::parse(
                    '2026-09-05'
                )
            );

        $this->assertNull(
            $actual
        );

        $this->assertStringContainsString(
            'Absence of a current assertion means working pattern is not established',
            WorkingPatternAssertion::TRUTH_BOUNDARY
        );
    }

    public function test_zero_minutes_is_explicit_non_working_day_not_unknown(): void
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

        $this->assertSame(
            0,
            $assertion->saturday_minutes
        );

        $this->assertSame(
            0,
            $assertion->weekdayMinutes()[
                'saturday'
            ]
        );

        $this->assertStringContainsString(
            'zero minutes on a day means an explicit recurring non-working day, not unknown',
            strtolower(
                WorkingPatternAssertion::TRUTH_BOUNDARY
            )
        );
    }

    public function test_confirmed_pattern_requires_every_weekday_value(): void
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
                'friday_minutes' => null,
            ]
        );
    }

    public function test_confirmed_pattern_requires_positive_weekly_total(): void
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
                'monday_minutes' => 0,

                'tuesday_minutes' => 0,

                'wednesday_minutes' => 0,

                'thursday_minutes' => 0,

                'friday_minutes' => 0,

                'saturday_minutes' => 0,

                'sunday_minutes' => 0,
            ]
        );
    }

    public function test_confirmed_day_cannot_exceed_twenty_four_hours(): void
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
                'monday_minutes' => 1441,
            ]
        );
    }

    public function test_no_fixed_pattern_is_explicit_truth_not_zero_schedule(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'pattern_status' => WorkingPatternAssertion::STATUS_NO_FIXED_PATTERN,

                'pattern_basis' => null,

                'monday_minutes' => null,

                'tuesday_minutes' => null,

                'wednesday_minutes' => null,

                'thursday_minutes' => null,

                'friday_minutes' => null,

                'saturday_minutes' => null,

                'sunday_minutes' => null,

                'reason' => 'Human confirmed no fixed recurring weekly distribution.',
            ]
        );

        $actual =
            app(
                WorkingPatternCurrentAssertionService::class
            )->forUser(
                user: $subject,
                asOf: CarbonImmutable::parse(
                    '2026-09-05'
                )
            );

        $this->assertNotNull(
            $actual
        );

        $this->assertSame(
            WorkingPatternAssertion::STATUS_NO_FIXED_PATTERN,
            $actual->pattern_status
        );

        $this->assertNull(
            $actual->scheduledMinutesPerWeek()
        );

        $this->assertSame(
            [],
            $actual->weekdayMinutes()
        );

        $boundary =
            strtolower(
                WorkingPatternAssertion::TRUTH_BOUNDARY
            );

        foreach (
            [
                'does not mean zero contracted capacity',
                'zero availability',
                'inactivity',
                'no employment',
            ] as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $boundary
            );
        }
    }

    public function test_no_fixed_pattern_must_not_carry_weekday_minutes(): void
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
                'pattern_status' => WorkingPatternAssertion::STATUS_NO_FIXED_PATTERN,

                'pattern_basis' => null,

                'monday_minutes' => 450,

                'tuesday_minutes' => null,

                'wednesday_minutes' => null,

                'thursday_minutes' => null,

                'friday_minutes' => null,

                'saturday_minutes' => null,

                'sunday_minutes' => null,
            ]
        );
    }

    public function test_future_successor_does_not_replace_current_pattern_early(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $current =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer
            );

        $future =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'supersedes_working_pattern_assertion_id' => $current->id,

                    'monday_minutes' => 360,

                    'tuesday_minutes' => 360,

                    'wednesday_minutes' => 360,

                    'thursday_minutes' => 360,

                    'friday_minutes' => 360,

                    'effective_from' => '2026-10-01',
                ]
            );

        $service =
            app(
                WorkingPatternCurrentAssertionService::class
            );

        $before =
            $service->forUser(
                user: $subject,
                asOf: CarbonImmutable::parse(
                    '2026-09-05'
                )
            );

        $after =
            $service->forUser(
                user: $subject,
                asOf: CarbonImmutable::parse(
                    '2026-10-01'
                )
            );

        $this->assertSame(
            (string) $current->id,
            (string) $before?->id
        );

        $this->assertSame(
            (string) $future->id,
            (string) $after?->id
        );

        $this->assertSame(
            1800,
            $after?->scheduledMinutesPerWeek()
        );
    }

    public function test_independent_overlapping_pattern_heads_fail_closed(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
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
            WorkingPatternCurrentAssertionService::class
        )->forUser(
            user: $subject,
            asOf: CarbonImmutable::parse(
                '2026-09-05'
            )
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

        $first =
            $this->assertion(
                subject: $firstUser,
                reviewer: $reviewer
            );

        $this->expectException(
            QueryException::class
        );

        $this->assertion(
            subject: $secondUser,
            reviewer: $reviewer,
            overrides: [
                'supersedes_working_pattern_assertion_id' => $first->id,
            ]
        );
    }

    public function test_invalid_effective_date_range_is_rejected(): void
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

    public function test_one_pattern_assertion_cannot_have_two_direct_successors(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $first =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer
            );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'supersedes_working_pattern_assertion_id' => $first->id,

                'effective_from' => '2026-10-01',
            ]
        );

        $this->expectException(
            QueryException::class
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'supersedes_working_pattern_assertion_id' => $first->id,

                'effective_from' => '2026-11-01',
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
            'monday_minutes' => 1,
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
            'working_pattern_assertions'
        )
            ->where(
                'id',
                $assertion->id
            )
            ->update([
                'monday_minutes' => 1,
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
            'working_pattern_assertions'
        )
            ->where(
                'id',
                $assertion->id
            )
            ->delete();
    }

    public function test_pattern_schema_contains_no_leave_availability_utilisation_or_billability_authority(): void
    {
        $columns =
            Schema::getColumnListing(
                'working_pattern_assertions'
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
                'leave',
                'absence',
                'holiday',
                'sickness',
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

    public function test_truth_boundary_keeps_working_pattern_bounded(): void
    {
        $boundary =
            strtolower(
                WorkingPatternAssertion::TRUTH_BOUNDARY
            );

        foreach (
            [
                'explicit human-confirmed recurring schedule truth',
                'every weekday',
                'explicit recurring non-working day',
                'not unknown',
                'no fixed recurring weekly distribution',
                'does not mean zero contracted capacity',
                'zero availability',
                'working pattern is not established',
                'does not establish contracted capacity',
                'leave',
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
        array $overrides = []
    ): WorkingPatternAssertion {
        return WorkingPatternAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'supersedes_working_pattern_assertion_id' => null,

                        'pattern_status' => WorkingPatternAssertion::STATUS_CONFIRMED,

                        'pattern_basis' => WorkingPatternAssertion::BASIS_WEEKLY,

                        'monday_minutes' => 450,

                        'tuesday_minutes' => 450,

                        'wednesday_minutes' => 450,

                        'thursday_minutes' => 450,

                        'friday_minutes' => 450,

                        'saturday_minutes' => 0,

                        'sunday_minutes' => 0,

                        'effective_from' => '2026-01-01',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'working-pattern-review',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 18:20:00',

                        'reason' => 'Explicit human-confirmed recurring working-pattern assertion.',

                        'metadata' => [
                            'scope' => 'working_pattern',
                        ],
                    ],
                    $overrides
                )
            );
    }
}
