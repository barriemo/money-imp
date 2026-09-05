<?php

namespace Tests\Feature;

use App\Domains\TeamTruth\Services\TeamMembershipCurrentAssertionService;
use App\Models\TeamMembershipAssertion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class TeamMembershipAssertionTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_assertion_is_exact_user_membership_truth_with_provenance(): void
    {
        $reviewer =
            User::factory()->create([
                'name' => 'Human Reviewer',
            ]);

        $subject =
            User::factory()->create([
                'name' => 'Subject User',
            ]);

        $other =
            User::factory()->create([
                'name' => 'Other User',
            ]);

        $expected =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'source' => 'human_confirmation',

                    'source_reference' => 'team-review-2026-09-05',

                    'reason' => 'Explicitly confirmed as a current team member.',
                ]
            );

        $this->assertion(
            subject: $other,
            reviewer: $reviewer,
            overrides: [
                'membership_status' => TeamMembershipAssertion::STATUS_NOT_MEMBER,
            ]
        );

        $actual =
            app(
                TeamMembershipCurrentAssertionService::class
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
            TeamMembershipAssertion::STATUS_MEMBER,
            $actual->membership_status
        );

        $this->assertSame(
            'human_confirmation',
            $actual->source
        );

        $this->assertSame(
            'team-review-2026-09-05',
            $actual->source_reference
        );

        $this->assertSame(
            (int) $reviewer->id,
            (int) $actual->reviewed_by
        );

        $this->assertSame(
            'Human Reviewer',
            $actual->reviewed_by_name
        );

        $this->assertSame(
            '2026-01-01',
            $actual->effective_from->toDateString()
        );
    }

    public function test_user_account_without_assertion_does_not_become_team_member(): void
    {
        $user =
            User::factory()->create();

        $actual =
            app(
                TeamMembershipCurrentAssertionService::class
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
            'Absence of a current assertion means membership is not established',
            TeamMembershipAssertion::TRUTH_BOUNDARY
        );
    }

    public function test_future_successor_does_not_replace_current_membership_early(): void
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
                    'membership_status' => TeamMembershipAssertion::STATUS_MEMBER,

                    'effective_from' => '2026-01-01',
                ]
            );

        $future =
            $this->assertion(
                subject: $subject,
                reviewer: $reviewer,
                overrides: [
                    'supersedes_team_membership_assertion_id' => $current->id,

                    'membership_status' => TeamMembershipAssertion::STATUS_NOT_MEMBER,

                    'effective_from' => '2026-10-01',

                    'reason' => 'Confirmed future end of membership.',
                ]
            );

        $service =
            app(
                TeamMembershipCurrentAssertionService::class
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
            TeamMembershipAssertion::STATUS_NOT_MEMBER,
            $after?->membership_status
        );
    }

    public function test_independent_overlapping_membership_heads_fail_closed(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'membership_status' => TeamMembershipAssertion::STATUS_MEMBER,

                'effective_from' => '2026-01-01',
            ]
        );

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'membership_status' => TeamMembershipAssertion::STATUS_NOT_MEMBER,

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
            TeamMembershipCurrentAssertionService::class
        )->forUser(
            user: $subject,
            asOf: CarbonImmutable::parse(
                '2026-09-05'
            )
        );
    }

    public function test_cross_user_supersession_fails_closed(): void
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

        $this->assertion(
            subject: $secondUser,
            reviewer: $reviewer,
            overrides: [
                'supersedes_team_membership_assertion_id' => $first->id,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'may supersede only an assertion for the same User'
        );

        app(
            TeamMembershipCurrentAssertionService::class
        )->forUser(
            user: $secondUser,
            asOf: CarbonImmutable::parse(
                '2026-09-05'
            )
        );
    }

    public function test_invalid_effective_date_range_fails_closed(): void
    {
        $reviewer =
            User::factory()->create();

        $subject =
            User::factory()->create();

        $this->assertion(
            subject: $subject,
            reviewer: $reviewer,
            overrides: [
                'effective_from' => '2026-10-01',

                'effective_to' => '2026-09-01',
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'invalid effective date range'
        );

        app(
            TeamMembershipCurrentAssertionService::class
        )->forUser(
            user: $subject,
            asOf: CarbonImmutable::parse(
                '2026-09-05'
            )
        );
    }

    public function test_one_assertion_cannot_have_two_direct_successors(): void
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
                'supersedes_team_membership_assertion_id' => $first->id,

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
                'supersedes_team_membership_assertion_id' => $first->id,

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
            'team_membership_assertions'
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
            'team_membership_assertions'
        )
            ->where(
                'id',
                $assertion->id
            )
            ->delete();
    }

    public function test_membership_schema_contains_no_capacity_or_billability_authority(): void
    {
        $columns =
            Schema::getColumnListing(
                'team_membership_assertions'
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
                'contracted_capacity',
                'capacity',
                'availability',
                'available_hours',
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

    public function test_truth_boundary_keeps_membership_bounded(): void
    {
        $boundary =
            strtolower(
                TeamMembershipAssertion::TRUTH_BOUNDARY
            );

        foreach (
            [
                'user account alone does not establish team membership',
                'does not establish employment',
                'verified work authorship',
                'contracted capacity',
                'available capacity',
                'utilisation',
                'billability',
                'absence of a current assertion means membership is not established',
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
    ): TeamMembershipAssertion {
        return TeamMembershipAssertion::query()
            ->create(
                array_merge(
                    [
                        'user_id' => $subject->id,

                        'supersedes_team_membership_assertion_id' => null,

                        'membership_status' => TeamMembershipAssertion::STATUS_MEMBER,

                        'effective_from' => '2026-01-01',

                        'effective_to' => null,

                        'source' => 'human_confirmation',

                        'source_reference' => 'team-review',

                        'reviewed_by' => $reviewer->id,

                        'reviewed_by_name' => $reviewer->name,

                        'reviewed_at' => '2026-09-05 12:00:00',

                        'reason' => 'Explicit human-confirmed team-membership assertion.',

                        'metadata' => [
                            'scope' => 'team_membership',
                        ],
                    ],
                    $overrides
                )
            );
    }
}
