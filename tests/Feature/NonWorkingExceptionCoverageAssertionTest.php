<?php

namespace Tests\Feature;

use App\Domains\TeamTruth\Services\NonWorkingExceptionCoverageCurrentAssertionService;
use App\Models\NonWorkingExceptionCoverageAssertion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class NonWorkingExceptionCoverageAssertionTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_assertion_is_exact_user_coverage_truth_with_provenance(): void
    {
        $reviewer =
            User::factory()->create([
                'name' => 'Coverage Reviewer',
            ]);

        $subject =
            User::factory()->create([
                'name' => 'Coverage Subject',
            ]);

        $other =
            User::factory()->create();

        $expected =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'covered_from' => '2026-09-01',

                    'covered_to' => '2026-09-30',

                    'source' => 'human_confirmation',

                    'source_reference' => 'capacity-calendar-review-2026-09',

                    'reason' => 'Exception ledger explicitly reviewed as complete for September.',
                ]
            );

        $this->assertion(
            subject: $other,
            reviewer: $reviewer,
            overrides: [
                'coverage_status' => NonWorkingExceptionCoverageAssertion::STATUS_NOT_COMPLETE,
            ]
        );

        $actual =
            app(
                NonWorkingExceptionCoverageCurrentAssertionService::class
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
            NonWorkingExceptionCoverageAssertion::STATUS_COMPLETE,
            $actual->coverage_status
        );

        $this->assertSame(
            '2026-09-01',
            $actual->covered_from->toDateString()
        );

        $this->assertSame(
            '2026-09-30',
            $actual->covered_to->toDateString()
        );

        $this->assertSame(
            'human_confirmation',
            $actual->source
        );

        $this->assertSame(
            'capacity-calendar-review-2026-09',
            $actual->source_reference
        );

        $this->assertSame(
            (int) $reviewer->id,
            (int) $actual->reviewed_by
        );

        $this->assertSame(
            'Coverage Reviewer',
            $actual->reviewed_by_name
        );
    }

    public function test_user_without_coverage_assertion_remains_unknown_not_complete_or_available(): void
    {
        $user =
            User::factory()->create();

        $actual =
            app(
                NonWorkingExceptionCoverageCurrentAssertionService::class
            )->forUser(
                user: $user,
                asOf: CarbonImmutable::parse(
                    '2026-09-05'
                )
            );

        $this->assertNull(
            $actual
        );

        $boundary =
            strtolower(
                NonWorkingExceptionCoverageAssertion::TRUTH_BOUNDARY
            );

        $this->assertStringContainsString(
            'absence of a current coverage assertion means coverage is unknown',
            $boundary
        );

        $this->assertStringContainsString(
            'available capacity',
            $boundary
        );
    }

    public function test_complete_coverage_has_an_exact_inclusive_window(): void
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
                    'covered_from' => '2026-09-07',

                    'covered_to' => '2026-09-13',
                ]
            );

        $this->assertSame(
            '2026-09-07',
            $assertion->covered_from->toDateString()
        );

        $this->assertSame(
            '2026-09-13',
            $assertion->covered_to->toDateString()
        );

        $this->assertStringContainsString(
            'explicit inclusive covered window',
            strtolower(
                NonWorkingExceptionCoverageAssertion::TRUTH_BOUNDARY
            )
        );
    }

    public function test_not_complete_is_explicit_truth_and_does_not_establish_zero_exception_effect(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'coverage_status' => NonWorkingExceptionCoverageAssertion::STATUS_NOT_COMPLETE,

                'reason' => 'Calendar review is explicitly incomplete.',
            ]
        );

        $actual =
            app(
                NonWorkingExceptionCoverageCurrentAssertionService::class
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
            NonWorkingExceptionCoverageAssertion::STATUS_NOT_COMPLETE,
            $actual->coverage_status
        );

        $boundary =
            strtolower(
                NonWorkingExceptionCoverageAssertion::TRUTH_BOUNDARY
            );

        $this->assertStringContainsString(
            'not_complete means that inference is forbidden',
            $boundary
        );
    }

    public function test_future_successor_does_not_replace_current_coverage_early(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $current =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'covered_from' => '2026-09-01',

                    'covered_to' => '2026-09-30',

                    'effective_from' => '2026-09-01',
                ]
            );

        $future =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'supersedes_non_working_exception_coverage_assertion_id' => $current->id,

                    'coverage_status' => NonWorkingExceptionCoverageAssertion::STATUS_NOT_COMPLETE,

                    'covered_from' => '2026-10-01',

                    'covered_to' => '2026-10-31',

                    'effective_from' => '2026-10-01',

                    'reason' => 'October coverage is not yet complete.',
                ]
            );

        $service =
            app(
                NonWorkingExceptionCoverageCurrentAssertionService::class
            );

        $before =
            $service->forUser(
                user: $subject,
                asOf: CarbonImmutable::parse(
                    '2026-09-30'
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
            NonWorkingExceptionCoverageAssertion::STATUS_NOT_COMPLETE,
            $after?->coverage_status
        );
    }

    public function test_independent_current_coverage_heads_for_same_user_fail_closed(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'covered_from' => '2026-09-01',

                'covered_to' => '2026-09-07',
            ]
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'covered_from' => '2026-09-08',

                'covered_to' => '2026-09-14',

                'effective_from' => '2026-09-02',
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'does not resolve to exactly one current assertion'
        );

        app(
            NonWorkingExceptionCoverageCurrentAssertionService::class
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
                'supersedes_non_working_exception_coverage_assertion_id' => $first->id,
            ]
        );
    }

    public function test_invalid_covered_date_range_is_rejected(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'invalid covered date range'
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'covered_from' => '2026-10-01',

                'covered_to' => '2026-09-30',
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

        $this->expectExceptionMessage(
            'invalid effective date range'
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'effective_from' => '2026-10-01',

                'effective_to' => '2026-09-30',
            ]
        );
    }

    public function test_one_coverage_assertion_cannot_have_two_direct_successors(): void
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
                'supersedes_non_working_exception_coverage_assertion_id' => $first->id,

                'covered_from' => '2026-10-01',

                'covered_to' => '2026-10-31',

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
                'supersedes_non_working_exception_coverage_assertion_id' => $first->id,

                'covered_from' => '2026-11-01',

                'covered_to' => '2026-11-30',

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
            'reason' => 'Silently changed',
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
            'non_working_exception_coverage_assertions'
        )
            ->where(
                'id',
                $assertion->id
            )
            ->update([
                'coverage_status' => NonWorkingExceptionCoverageAssertion::STATUS_NOT_COMPLETE,
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
            'non_working_exception_coverage_assertions'
        )
            ->where(
                'id',
                $assertion->id
            )
            ->delete();
    }

    public function test_coverage_schema_contains_no_availability_utilisation_or_billability_authority(): void
    {
        $columns =
            Schema::getColumnListing(
                'non_working_exception_coverage_assertions'
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
                'membership',
                'working_pattern',
                'contracted_capacity',
                'scheduled_minutes',
                'non_working_minutes',
                'available_minutes',
                'available_capacity',
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

    public function test_truth_boundary_keeps_coverage_bounded(): void
    {
        $boundary =
            strtolower(
                NonWorkingExceptionCoverageAssertion::TRUTH_BOUNDARY
            );

        foreach (
            [
                'explicit human- or source-confirmed completeness truth',
                'explicit inclusive covered window',
                'only inside that window',
                'zero confirmed non-working-exception effect',
                'not_complete means that inference is forbidden',
                'coverage is unknown',
                'one contiguous current coverage window per user',
                'does not union independent coverage assertions',
                'does not establish that no leave or absence exists in reality',
                'does not establish team membership',
                'employment',
                'contracted capacity',
                'working pattern',
                'scheduled minutes',
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
    ): NonWorkingExceptionCoverageAssertion {
        return NonWorkingExceptionCoverageAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'supersedes_non_working_exception_coverage_assertion_id' => null,

                        'coverage_status' => NonWorkingExceptionCoverageAssertion::STATUS_COMPLETE,

                        'covered_from' => '2026-09-01',

                        'covered_to' => '2026-09-30',

                        'effective_from' => '2026-09-01',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'non-working-exception-coverage-review',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 19:00:00',

                        'reason' => 'Explicit review of non-working-exception coverage completeness.',

                        'metadata' => [
                            'scope' => 'non_working_exception_coverage',
                        ],
                    ],
                    $overrides
                )
            );
    }
}
