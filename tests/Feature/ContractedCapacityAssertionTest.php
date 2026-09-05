<?php

namespace Tests\Feature;

use App\Domains\TeamTruth\Services\ContractedCapacityCurrentAssertionService;
use App\Models\ContractedCapacityAssertion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class ContractedCapacityAssertionTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_assertion_contains_exact_user_contracted_capacity_with_provenance(): void
    {
        $reviewer =
            User::factory()->create([
                'name' => 'Capacity Reviewer',
            ]);

        $subject =
            User::factory()->create([
                'name' => 'Capacity Subject',
            ]);

        $other =
            User::factory()->create();

        $expected =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'contracted_minutes' => 2250,

                    'period_basis' => ContractedCapacityAssertion::BASIS_WEEKLY,

                    'source' => 'human_confirmation',

                    'source_reference' => 'employment-terms-2026',

                    'reason' => 'Explicitly confirmed 37.5 contracted hours per week.',
                ]
            );

        $this->assertion(
            subject: $other,
            reviewer: $reviewer,
            overrides: [
                'contracted_minutes' => 1200,
            ]
        );

        $actual =
            app(
                ContractedCapacityCurrentAssertionService::class
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
            ContractedCapacityAssertion::STATUS_CONFIRMED,
            $actual->capacity_status
        );

        $this->assertSame(
            2250,
            $actual->contracted_minutes
        );

        $this->assertSame(
            ContractedCapacityAssertion::BASIS_WEEKLY,
            $actual->period_basis
        );

        $this->assertSame(
            'human_confirmation',
            $actual->source
        );

        $this->assertSame(
            'employment-terms-2026',
            $actual->source_reference
        );

        $this->assertSame(
            (int) $reviewer->id,
            (int) $actual->reviewed_by
        );

        $this->assertSame(
            'Capacity Reviewer',
            $actual->reviewed_by_name
        );

        $this->assertSame(
            '2026-01-01',
            $actual->effective_from->toDateString()
        );
    }

    public function test_user_without_capacity_assertion_remains_unknown_not_zero(): void
    {
        $user =
            User::factory()->create();

        $actual =
            app(
                ContractedCapacityCurrentAssertionService::class
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
                ContractedCapacityAssertion::TRUTH_BOUNDARY
            );

        $this->assertStringContainsString(
            'absence of a current assertion means contracted capacity is not established',
            $boundary
        );
    }

    public function test_no_fixed_capacity_is_explicit_truth_not_zero_capacity(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'capacity_status' => ContractedCapacityAssertion::STATUS_NO_FIXED_CAPACITY,

                'contracted_minutes' => null,

                'period_basis' => null,

                'reason' => 'Human confirmed that no fixed contracted working-capacity denominator applies.',
            ]
        );

        $actual =
            app(
                ContractedCapacityCurrentAssertionService::class
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
            ContractedCapacityAssertion::STATUS_NO_FIXED_CAPACITY,
            $actual->capacity_status
        );

        $this->assertNull(
            $actual->contracted_minutes
        );

        $this->assertNull(
            $actual->period_basis
        );

        $boundary =
            strtolower(
                ContractedCapacityAssertion::TRUTH_BOUNDARY
            );

        foreach (
            [
                'does not mean zero availability',
                'zero work',
                'no employment',
            ] as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $boundary
            );
        }
    }

    public function test_future_successor_does_not_replace_current_capacity_early(): void
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
                    'contracted_minutes' => 2250,

                    'effective_from' => '2026-01-01',
                ]
            );

        $future =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'supersedes_contracted_capacity_assertion_id' => $current->id,

                    'contracted_minutes' => 1800,

                    'effective_from' => '2026-10-01',

                    'reason' => 'Confirmed future change to 30 hours per week.',
                ]
            );

        $service =
            app(
                ContractedCapacityCurrentAssertionService::class
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
            $after?->contracted_minutes
        );
    }

    public function test_independent_overlapping_capacity_heads_fail_closed(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'contracted_minutes' => 2250,
            ]
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'contracted_minutes' => 1800,

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
            ContractedCapacityCurrentAssertionService::class
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
                'supersedes_contracted_capacity_assertion_id' => $first->id,
            ]
        );
    }

    public function test_confirmed_capacity_requires_positive_minutes(): void
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
                'contracted_minutes' => 0,
            ]
        );
    }

    public function test_confirmed_capacity_requires_explicit_period_basis(): void
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
                'period_basis' => null,
            ]
        );
    }

    public function test_no_fixed_capacity_must_not_carry_minutes(): void
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
                'capacity_status' => ContractedCapacityAssertion::STATUS_NO_FIXED_CAPACITY,

                'contracted_minutes' => 2250,

                'period_basis' => null,
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

    public function test_one_capacity_assertion_cannot_have_two_direct_successors(): void
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
                'supersedes_contracted_capacity_assertion_id' => $first->id,

                'contracted_minutes' => 1800,

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
                'supersedes_contracted_capacity_assertion_id' => $first->id,

                'contracted_minutes' => 1500,

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
            'contracted_minutes' => 1,
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
            'contracted_capacity_assertions'
        )
            ->where(
                'id',
                $assertion->id
            )
            ->update([
                'contracted_minutes' => 1,
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
            'contracted_capacity_assertions'
        )
            ->where(
                'id',
                $assertion->id
            )
            ->delete();
    }

    public function test_capacity_schema_contains_no_availability_utilisation_or_billability_authority(): void
    {
        $columns =
            Schema::getColumnListing(
                'contracted_capacity_assertions'
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
                'working_pattern',
                'working_days',
                'leave',
                'absence',
                'availability',
                'available_hours',
                'available_minutes',
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

    public function test_truth_boundary_keeps_contracted_capacity_bounded(): void
    {
        $boundary =
            strtolower(
                ContractedCapacityAssertion::TRUTH_BOUNDARY
            );

        foreach (
            [
                'explicit human-confirmed working-capacity truth',
                'positive contracted minutes',
                'explicit period basis',
                'no fixed contracted working-capacity denominator',
                'does not mean zero availability',
                'absence of a current assertion means contracted capacity is not established',
                'does not establish working pattern',
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
    ): ContractedCapacityAssertion {
        return ContractedCapacityAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'supersedes_contracted_capacity_assertion_id' => null,

                        'capacity_status' => ContractedCapacityAssertion::STATUS_CONFIRMED,

                        'contracted_minutes' => 2250,

                        'period_basis' => ContractedCapacityAssertion::BASIS_WEEKLY,

                        'effective_from' => '2026-01-01',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'capacity-review',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 12:00:00',

                        'reason' => 'Explicit human-confirmed contracted-capacity assertion.',

                        'metadata' => [
                            'scope' => 'contracted_capacity',
                        ],
                    ],
                    $overrides
                )
            );
    }
}
