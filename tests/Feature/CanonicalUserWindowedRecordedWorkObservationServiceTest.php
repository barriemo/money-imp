<?php

namespace Tests\Feature;

use App\Domains\WorkIntelligence\CanonicalUserWindowedRecordedWorkObservation;
use App\Domains\WorkIntelligence\CanonicalUserWindowedRecordedWorkObservationService;
use App\Models\Client;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use ReflectionClass;
use Tests\TestCase;

final class CanonicalUserWindowedRecordedWorkObservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_user_and_exact_window_include_only_matching_recorded_work(): void
    {
        $user =
            User::factory()->create([
                'name' => 'Windowed Work User',
            ]);

        $other =
            User::factory()->create([
                'name' => 'Other User',
            ]);

        $clientA =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $clientB =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $this->logWork(
            user: $user,
            client: $clientA,
            minutes: 20,
            performedAt: '2026-08-31'
        );

        $this->logWork(
            user: $user,
            client: $clientA,
            minutes: 45,
            performedAt: '2026-09-01'
        );

        $this->logWork(
            user: $user,
            client: $clientB,
            minutes: 30,
            performedAt: '2026-09-03'
        );

        $this->logWork(
            user: $user,
            client: $clientA,
            minutes: 15,
            performedAt: '2026-09-06'
        );

        $this->logWork(
            user: $other,
            client: $clientA,
            minutes: 999,
            performedAt: '2026-09-02'
        );

        $observedAt =
            CarbonImmutable::parse(
                '2026-09-05 21:00:00'
            );

        $observation =
            app(
                CanonicalUserWindowedRecordedWorkObservationService::class
            )->forUser(
                user: $user,
                startsOn: CarbonImmutable::parse(
                    '2026-09-01'
                ),
                endsOn: CarbonImmutable::parse(
                    '2026-09-05'
                ),
                observedAt: $observedAt
            );

        $this->assertSame(
            (int) $user->id,
            $observation->attributedUserId
        );

        $this->assertSame(
            'Windowed Work User',
            $observation->attributedUserName
        );

        $this->assertSame(
            '2026-09-01',
            $observation->startsOn
        );

        $this->assertSame(
            '2026-09-05',
            $observation->endsOn
        );

        $this->assertSame(
            2,
            $observation->recordedWorkLogCount
        );

        $this->assertSame(
            75,
            $observation->recordedMinutes
        );

        $this->assertSame(
            2,
            $observation->distinctRecordedClientCount
        );

        $this->assertSame(
            '2026-09-01',
            $observation->firstRecordedWorkOn
        );

        $this->assertSame(
            '2026-09-03',
            $observation->lastRecordedWorkOn
        );

        $this->assertSame(
            $observedAt,
            $observation->observedAt
        );
    }

    public function test_window_boundaries_are_inclusive(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $this->logWork(
            user: $user,
            client: $client,
            minutes: 30,
            performedAt: '2026-09-01'
        );

        $this->logWork(
            user: $user,
            client: $client,
            minutes: 45,
            performedAt: '2026-09-05'
        );

        $observation =
            $this->observe(
                user: $user,
                startsOn: '2026-09-01',
                endsOn: '2026-09-05'
            );

        $this->assertSame(
            2,
            $observation->recordedWorkLogCount
        );

        $this->assertSame(
            75,
            $observation->recordedMinutes
        );

        $this->assertSame(
            '2026-09-01',
            $observation->firstRecordedWorkOn
        );

        $this->assertSame(
            '2026-09-05',
            $observation->lastRecordedWorkOn
        );
    }

    public function test_one_day_window_is_exact(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $this->logWork(
            user: $user,
            client: $client,
            minutes: 60,
            performedAt: '2026-09-04'
        );

        $this->logWork(
            user: $user,
            client: $client,
            minutes: 999,
            performedAt: '2026-09-05'
        );

        $observation =
            $this->observe(
                user: $user,
                startsOn: '2026-09-04',
                endsOn: '2026-09-04'
            );

        $this->assertSame(
            1,
            $observation->recordedWorkLogCount
        );

        $this->assertSame(
            60,
            $observation->recordedMinutes
        );
    }

    public function test_zero_recorded_work_means_only_zero_recorded_work_inside_exact_window(): void
    {
        $user =
            User::factory()->create();

        $observation =
            $this->observe(
                user: $user,
                startsOn: '2026-09-01',
                endsOn: '2026-09-05'
            );

        $this->assertSame(
            0,
            $observation->recordedWorkLogCount
        );

        $this->assertSame(
            0,
            $observation->recordedMinutes
        );

        $this->assertSame(
            0,
            $observation->distinctRecordedClientCount
        );

        $this->assertNull(
            $observation->firstRecordedWorkOn
        );

        $this->assertNull(
            $observation->lastRecordedWorkOn
        );

        $boundary =
            strtolower(
                $observation->truthBoundary
            );

        $this->assertStringContainsString(
            'zero recorded minutes means only that zero worklog minutes are recorded',
            $boundary
        );

        $this->assertStringContainsString(
            'does not prove that no work occurred',
            $boundary
        );

        $this->assertStringContainsString(
            'inactivity',
            $boundary
        );

        $this->assertStringContainsString(
            'free capacity',
            $boundary
        );
    }

    public function test_recorded_work_outside_window_does_not_leak_into_observation(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $this->logWork(
            user: $user,
            client: $client,
            minutes: 500,
            performedAt: '2026-08-31'
        );

        $this->logWork(
            user: $user,
            client: $client,
            minutes: 700,
            performedAt: '2026-09-06'
        );

        $observation =
            $this->observe(
                user: $user,
                startsOn: '2026-09-01',
                endsOn: '2026-09-05'
            );

        $this->assertSame(
            0,
            $observation->recordedMinutes
        );
    }

    public function test_invalid_reverse_window_fails_closed(): void
    {
        $user =
            User::factory()->create();

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'end date on or after the start date'
        );

        $this->observe(
            user: $user,
            startsOn: '2026-09-05',
            endsOn: '2026-09-01'
        );
    }

    public function test_truth_boundary_says_recorded_work_is_not_complete_work_truth_or_utilisation(): void
    {
        $boundary =
            strtolower(
                CanonicalUserWindowedRecordedWorkObservation::TRUTH_BOUNDARY
            );

        foreach (
            [
                'explicit inclusive date window',
                'recorded attribution',
                'not server-verified proof',
                'does not establish that every minute actually worked',
                'zero recorded minutes',
                'does not prove that no work occurred',
                'employment',
                'team membership',
                'available capacity',
                'utilisation',
                'allocation',
                'billability',
                'productivity',
                'performance',
            ] as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $boundary
            );
        }
    }

    public function test_observation_exposes_no_capacity_utilisation_billability_or_performance_authority(): void
    {
        $reflection =
            new ReflectionClass(
                CanonicalUserWindowedRecordedWorkObservation::class
            );

        foreach (
            [
                'teamMember',
                'employmentStatus',
                'contractedCapacity',
                'availableCapacity',
                'availableMinutes',
                'capacity',
                'utilisation',
                'utilization',
                'utilisationPercentage',
                'utilizationPercentage',
                'allocatedMinutes',
                'unallocatedMinutes',
                'freeCapacity',
                'billable',
                'billability',
                'recoverableValue',
                'cost',
                'margin',
                'productivity',
                'performance',
                'priority',
                'recommendation',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    public function test_service_is_read_only_and_has_no_capacity_resource_or_billability_dependency(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/WorkIntelligence/CanonicalUserWindowedRecordedWorkObservationService.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'AvailableCapacity',
                'TeamMembership',
                'ContractedCapacity',
                'WorkingPattern',
                'NonWorkingException',
                'ResourceIntelligence',
                'ResourceAllocation',
                'BillabilityReasoner',
                'BillabilityAssessment',
                'utilisation',
                'utilization',
                'margin',
                'costRate',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function observe(
        User $user,
        string $startsOn,
        string $endsOn,
    ): CanonicalUserWindowedRecordedWorkObservation {
        return app(
            CanonicalUserWindowedRecordedWorkObservationService::class
        )->forUser(
            user: $user,
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

    private function logWork(
        User $user,
        Client $client,
        int $minutes,
        string $performedAt,
    ): void {
        $response =
            $this
                ->actingAs(
                    $user
                )
                ->post(
                    route(
                        'work-log.store'
                    ),
                    [
                        'client_id' => $client->id,

                        'user_id' => $user->id,

                        'description' => 'Windowed recorded-work test',

                        'minutes' => $minutes,

                        'performed_at' => $performedAt,

                        'billing_hint' => 'billable',
                    ]
                );

        $response
            ->assertRedirect();
    }
}
