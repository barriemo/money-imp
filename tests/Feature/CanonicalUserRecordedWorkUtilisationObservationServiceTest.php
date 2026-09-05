<?php

namespace Tests\Feature;

use App\Domains\WorkIntelligence\CanonicalUserRecordedWorkUtilisationObservation;
use App\Domains\WorkIntelligence\CanonicalUserRecordedWorkUtilisationObservationService;
use App\Models\Client;
use App\Models\ContractedCapacityAssertion;
use App\Models\NonWorkingExceptionCoverageAssertion;
use App\Models\TeamMembershipAssertion;
use App\Models\User;
use App\Models\WorkingPatternAssertion;
use App\Models\WorkLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use ReflectionClass;
use Tests\TestCase;

final class CanonicalUserRecordedWorkUtilisationObservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_user_and_window_derive_recorded_work_utilisation(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableCapacityTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $this->recordWork(
            subject: $subject,
            client: $client,
            minutes: 225,
            performedAt: '2026-09-07'
        );

        $observedAt =
            CarbonImmutable::parse(
                '2026-09-05 21:00:00'
            );

        $observation =
            app(
                CanonicalUserRecordedWorkUtilisationObservationService::class
            )->forUser(
                user: $subject,
                startsOn: CarbonImmutable::parse(
                    '2026-09-07'
                ),
                endsOn: CarbonImmutable::parse(
                    '2026-09-07'
                ),
                observedAt: $observedAt
            );

        $this->assertSame(
            (int) $subject->id,
            $observation->userId
        );

        $this->assertSame(
            '2026-09-07',
            $observation->startsOn
        );

        $this->assertSame(
            '2026-09-07',
            $observation->endsOn
        );

        $this->assertSame(
            225,
            $observation->recordedMinutes
        );

        $this->assertSame(
            450,
            $observation->availableMinutes
        );

        $this->assertSame(
            5000,
            $observation->recordedWorkUtilisationBasisPoints
        );

        $this->assertSame(
            $observedAt,
            $observation->observedAt
        );
    }

    public function test_zero_recorded_minutes_with_positive_available_capacity_is_zero_recorded_work_utilisation_only(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableCapacityTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-07',
                endsOn: '2026-09-07'
            );

        $this->assertSame(
            0,
            $observation->recordedMinutes
        );

        $this->assertSame(
            450,
            $observation->availableMinutes
        );

        $this->assertSame(
            0,
            $observation->recordedWorkUtilisationBasisPoints
        );

        $boundary =
            strtolower(
                $observation->truthBoundary
            );

        $this->assertStringContainsString(
            'zero recorded minutes against positive calendar-available minutes produces zero recorded-work utilisation only',
            $boundary
        );

        $this->assertStringContainsString(
            'does not prove that no work occurred',
            $boundary
        );

        $this->assertStringContainsString(
            'idleness',
            $boundary
        );

        $this->assertStringContainsString(
            'under-performance',
            $boundary
        );
    }

    public function test_zero_available_minutes_make_ratio_non_derivable_instead_of_zero_percent(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableCapacityTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'not derivable when calendar-available minutes are zero'
        );

        $this->observe(
            subject: $subject,
            startsOn: '2026-09-12',
            endsOn: '2026-09-12'
        );
    }

    public function test_recorded_work_utilisation_may_exceed_one_hundred_percent_without_capping(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableCapacityTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $this->recordWork(
            subject: $subject,
            client: $client,
            minutes: 900,
            performedAt: '2026-09-07'
        );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-07',
                endsOn: '2026-09-07'
            );

        $this->assertSame(
            900,
            $observation->recordedMinutes
        );

        $this->assertSame(
            450,
            $observation->availableMinutes
        );

        $this->assertSame(
            20000,
            $observation->recordedWorkUtilisationBasisPoints
        );

        $this->assertGreaterThan(
            CanonicalUserRecordedWorkUtilisationObservation::BASIS_POINTS_PER_HUNDRED_PERCENT,
            $observation->recordedWorkUtilisationBasisPoints
        );
    }

    public function test_basis_points_round_half_up_without_storing_a_float_percentage(): void
    {
        $this->assertSame(
            3333,
            CanonicalUserRecordedWorkUtilisationObservation::basisPointsFor(
                recordedMinutes: 1,
                availableMinutes: 3
            )
        );

        $this->assertSame(
            6667,
            CanonicalUserRecordedWorkUtilisationObservation::basisPointsFor(
                recordedMinutes: 2,
                availableMinutes: 3
            )
        );

        $this->assertSame(
            10000,
            CanonicalUserRecordedWorkUtilisationObservation::basisPointsFor(
                recordedMinutes: 450,
                availableMinutes: 450
            )
        );

        $this->assertSame(
            20000,
            CanonicalUserRecordedWorkUtilisationObservation::basisPointsFor(
                recordedMinutes: 900,
                availableMinutes: 450
            )
        );
    }

    public function test_work_outside_exact_window_does_not_enter_ratio_numerator(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $this->seedDerivableCapacityTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $this->recordWork(
            subject: $subject,
            client: $client,
            minutes: 999,
            performedAt: '2026-09-06'
        );

        $this->recordWork(
            subject: $subject,
            client: $client,
            minutes: 225,
            performedAt: '2026-09-07'
        );

        $this->recordWork(
            subject: $subject,
            client: $client,
            minutes: 999,
            performedAt: '2026-09-08'
        );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-07',
                endsOn: '2026-09-07'
            );

        $this->assertSame(
            225,
            $observation->recordedMinutes
        );

        $this->assertSame(
            5000,
            $observation->recordedWorkUtilisationBasisPoints
        );
    }

    public function test_other_users_recorded_work_does_not_enter_ratio_numerator(): void
    {
        [$subject, $reviewer] =
            $this->users();

        $other =
            User::factory()->create([
                'name' => 'Other Recorded Work User',
            ]);

        $this->seedDerivableCapacityTruth(
            subject: $subject,
            reviewer: $reviewer
        );

        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $this->recordWork(
            subject: $other,
            client: $client,
            minutes: 900,
            performedAt: '2026-09-07'
        );

        $this->recordWork(
            subject: $subject,
            client: $client,
            minutes: 225,
            performedAt: '2026-09-07'
        );

        $observation =
            $this->observe(
                subject: $subject,
                startsOn: '2026-09-07',
                endsOn: '2026-09-07'
            );

        $this->assertSame(
            225,
            $observation->recordedMinutes
        );

        $this->assertSame(
            5000,
            $observation->recordedWorkUtilisationBasisPoints
        );
    }

    public function test_truth_boundary_keeps_recorded_work_utilisation_bounded(): void
    {
        $boundary =
            strtolower(
                CanonicalUserRecordedWorkUtilisationObservation::TRUTH_BOUNDARY
            );

        foreach (
            [
                'one exact user',
                'one exact inclusive date window',
                'does not establish that every minute actually worked was recorded',
                'does not verify performer identity',
                'calendar-available scheduled capacity',
                'does not mean free',
                'zero calendar-available minutes',
                'non-derivable',
                'may exceed 100%',
                'must not be capped',
                'actual or complete utilisation',
                'complete work capture',
                'attendance',
                'productivity',
                'performance',
                'unallocated capacity',
                'allocation',
                'billability',
                'recoverability',
                'cost',
                'margin',
                'priority',
                'recommendation',
                'management action',
            ] as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $boundary
            );
        }
    }

    public function test_observation_exposes_no_performance_billability_allocation_or_recommendation_authority(): void
    {
        $reflection =
            new ReflectionClass(
                CanonicalUserRecordedWorkUtilisationObservation::class
            );

        foreach (
            [
                'actualUtilisation',
                'completeUtilisation',
                'productivity',
                'performance',
                'freeCapacity',
                'freeMinutes',
                'unallocatedCapacity',
                'unallocatedMinutes',
                'allocation',
                'allocatedMinutes',
                'billable',
                'billability',
                'recoverableValue',
                'cost',
                'margin',
                'priority',
                'recommendation',
                'recommendedAction',
                'managementAction',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    public function test_composition_service_uses_only_released_canonical_inputs_and_is_read_only(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/WorkIntelligence/CanonicalUserRecordedWorkUtilisationObservationService.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'CanonicalUserWindowedRecordedWorkObservationService',
                'CanonicalUserAvailableCapacityObservationService',
                'assertExactIdentity',
            ] as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach (
            [
                'WorkLog::',
                'TeamMembershipAssertion::',
                'ContractedCapacityAssertion::',
                'WorkingPatternAssertion::',
                'NonWorkingExceptionAssertion::',
                'ResourceIntelligence',
                'ResourceAllocation',
                'BillabilityReasoner',
                'BillabilityAssessment',
                'performanceScore',
                'priorityScore',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function observe(
        User $subject,
        string $startsOn,
        string $endsOn,
    ): CanonicalUserRecordedWorkUtilisationObservation {
        return app(
            CanonicalUserRecordedWorkUtilisationObservationService::class
        )->forUser(
            user: $subject,
            startsOn: CarbonImmutable::parse(
                $startsOn
            ),
            endsOn: CarbonImmutable::parse(
                $endsOn
            ),
            observedAt: CarbonImmutable::parse(
                '2026-09-05 21:00:00'
            )
        );
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function users(): array
    {
        return [
            User::factory()->create([
                'name' => 'Recorded Work Utilisation Subject',
            ]),

            User::factory()->create([
                'name' => 'Recorded Work Utilisation Reviewer',
            ]),
        ];
    }

    private function seedDerivableCapacityTruth(
        User $subject,
        User $reviewer,
    ): void {
        TeamMembershipAssertion::query()
            ->create([
                'user_id' => $subject->id,

                'supersedes_team_membership_assertion_id' => null,

                'membership_status' => TeamMembershipAssertion::STATUS_MEMBER,

                'effective_from' => '2026-01-01',

                'effective_to' => null,

                'source' => 'human_confirmation',

                'source_reference' => 'recorded-work-utilisation-membership',

                'reviewed_by' => $reviewer->id,

                'reviewed_by_name' => $reviewer->name,

                'reviewed_at' => '2026-09-05 21:00:00',

                'reason' => 'Explicit membership truth for recorded-work utilisation test.',

                'metadata' => [
                    'scope' => 'recorded_work_utilisation_test',
                ],
            ]);

        ContractedCapacityAssertion::query()
            ->create([
                'user_id' => $subject->id,

                'supersedes_contracted_capacity_assertion_id' => null,

                'capacity_status' => ContractedCapacityAssertion::STATUS_CONFIRMED,

                'contracted_minutes' => 2250,

                'period_basis' => ContractedCapacityAssertion::BASIS_WEEKLY,

                'effective_from' => '2026-01-01',

                'effective_to' => null,

                'source' => 'human_confirmation',

                'source_reference' => 'recorded-work-utilisation-capacity',

                'reviewed_by' => $reviewer->id,

                'reviewed_by_name' => $reviewer->name,

                'reviewed_at' => '2026-09-05 21:00:00',

                'reason' => 'Explicit capacity truth for recorded-work utilisation test.',

                'metadata' => [
                    'scope' => 'recorded_work_utilisation_test',
                ],
            ]);

        WorkingPatternAssertion::query()
            ->create([
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

                'source_reference' => 'recorded-work-utilisation-pattern',

                'reviewed_by' => $reviewer->id,

                'reviewed_by_name' => $reviewer->name,

                'reviewed_at' => '2026-09-05 21:00:00',

                'reason' => 'Explicit working-pattern truth for recorded-work utilisation test.',

                'metadata' => [
                    'scope' => 'recorded_work_utilisation_test',
                ],
            ]);

        NonWorkingExceptionCoverageAssertion::query()
            ->create([
                'user_id' => $subject->id,

                'supersedes_non_working_exception_coverage_assertion_id' => null,

                'coverage_status' => NonWorkingExceptionCoverageAssertion::STATUS_COMPLETE,

                'covered_from' => '2026-09-01',

                'covered_to' => '2026-09-30',

                'effective_from' => '2026-01-01',

                'effective_to' => null,

                'source' => 'human_confirmation',

                'source_reference' => 'recorded-work-utilisation-exception-coverage',

                'reviewed_by' => $reviewer->id,

                'reviewed_by_name' => $reviewer->name,

                'reviewed_at' => '2026-09-05 21:00:00',

                'reason' => 'Explicit exception-coverage truth for recorded-work utilisation test.',

                'metadata' => [
                    'scope' => 'recorded_work_utilisation_test',
                ],
            ]);
    }

    private function recordWork(
        User $subject,
        Client $client,
        int $minutes,
        string $performedAt,
    ): void {
        WorkLog::query()
            ->create([
                'client_id' => $client->id,

                'user_id' => $subject->id,

                'description' => 'Recorded-work utilisation test',

                'minutes' => $minutes,

                'performed_at' => $performedAt,

                'billing_hint' => 'billable',
            ]);
    }
}
