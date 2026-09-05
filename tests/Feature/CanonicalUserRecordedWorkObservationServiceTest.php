<?php

namespace Tests\Feature;

use App\Domains\WorkIntelligence\CanonicalUserRecordedWorkObservation;
use App\Domains\WorkIntelligence\CanonicalUserRecordedWorkObservationService;
use App\Models\Client;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class CanonicalUserRecordedWorkObservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_user_observation_contains_only_recorded_work_facts(): void
    {
        $user =
            User::factory()->create([
                'name' => 'Recorded Work User',
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
            description: 'First recorded task',
            minutes: 45,
            performedAt: '2026-09-01'
        );

        $this->logWork(
            user: $user,
            client: $clientB,
            description: 'Second recorded task',
            minutes: 30,
            performedAt: '2026-09-03'
        );

        $this->logWork(
            user: $other,
            client: $clientA,
            description: 'Other user task',
            minutes: 999,
            performedAt: '2026-09-04'
        );

        $asOf =
            CarbonImmutable::parse(
                '2026-09-05 17:00:00'
            );

        $observation =
            app(
                CanonicalUserRecordedWorkObservationService::class
            )->forUser(
                $user,
                $asOf
            );

        $this->assertSame(
            (int) $user->id,
            $observation->attributedUserId
        );

        $this->assertSame(
            'Recorded Work User',
            $observation->attributedUserName
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
            $asOf,
            $observation->observedAt
        );

        $this->assertSame(
            CanonicalUserRecordedWorkObservation::TRUTH_BOUNDARY,
            $observation->truthBoundary
        );
    }

    public function test_user_id_is_recorded_attribution_not_verified_authenticated_actor_identity(): void
    {
        $authenticatedUser =
            User::factory()->create([
                'name' => 'Authenticated User',
            ]);

        $attributedUser =
            User::factory()->create([
                'name' => 'Attributed User',
            ]);

        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $response =
            $this
                ->actingAs(
                    $authenticatedUser
                )
                ->post(
                    route(
                        'work-log.store'
                    ),
                    [
                        'client_id' => $client->id,

                        'user_id' => $attributedUser->id,

                        'description' => 'Caller attributed this work to another user',

                        'minutes' => 30,

                        'performed_at' => '2026-09-04',

                        'billing_hint' => 'billable',
                    ]
                );

        $response
            ->assertRedirect();

        $attributedObservation =
            app(
                CanonicalUserRecordedWorkObservationService::class
            )->forUser(
                $attributedUser
            );

        $authenticatedObservation =
            app(
                CanonicalUserRecordedWorkObservationService::class
            )->forUser(
                $authenticatedUser
            );

        $this->assertSame(
            1,
            $attributedObservation->recordedWorkLogCount
        );

        $this->assertSame(
            30,
            $attributedObservation->recordedMinutes
        );

        $this->assertSame(
            (int) $attributedUser->id,
            $attributedObservation->attributedUserId
        );

        $this->assertSame(
            'Attributed User',
            $attributedObservation->attributedUserName
        );

        $this->assertSame(
            0,
            $authenticatedObservation->recordedWorkLogCount
        );

        $this->assertStringContainsString(
            'not server-verified proof that the attributed user performed the work',
            strtolower(
                $attributedObservation->truthBoundary
            )
        );

        $this->assertStringContainsString(
            'caller-selected existing user id',
            strtolower(
                $attributedObservation->truthBoundary
            )
        );
    }

    public function test_zero_recorded_work_does_not_become_inactivity_or_availability(): void
    {
        $user =
            User::factory()->create([
                'name' => 'No Recorded Work User',
            ]);

        $observation =
            app(
                CanonicalUserRecordedWorkObservationService::class
            )->forUser(
                $user
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

        $this->assertStringContainsString(
            'no recorded work attributed to a user does not prove inactivity or availability',
            strtolower(
                $observation->truthBoundary
            )
        );
    }

    public function test_contract_exposes_no_capacity_utilisation_billability_or_performance_authority(): void
    {
        $reflection =
            new ReflectionClass(
                CanonicalUserRecordedWorkObservation::class
            );

        foreach (
            [
                'teamMember',
                'employmentStatus',
                'contractedCapacity',
                'availableCapacity',
                'capacity',
                'utilisation',
                'utilization',
                'overAllocated',
                'underUtilised',
                'underUtilized',
                'billable',
                'billability',
                'recoverableValue',
                'cost',
                'costRate',
                'margin',
                'marginPercentage',
                'performance',
                'priority',
                'recommendation',
                'recommendedAction',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    public function test_truth_boundary_preserves_recorded_work_vs_team_authority(): void
    {
        $boundary =
            strtolower(
                CanonicalUserRecordedWorkObservation::TRUTH_BOUNDARY
            );

        $this->assertStringContainsString(
            'does not by itself establish employment or team membership',
            $boundary
        );

        $this->assertStringContainsString(
            'does not establish contracted capacity',
            $boundary
        );

        $this->assertStringContainsString(
            'available capacity',
            $boundary
        );

        $this->assertStringContainsString(
            'utilisation',
            $boundary
        );

        $this->assertStringContainsString(
            'billability',
            $boundary
        );

        $this->assertStringContainsString(
            'performance',
            $boundary
        );
    }

    public function test_service_does_not_depend_on_resource_or_billability_models(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/WorkIntelligence/CanonicalUserRecordedWorkObservationService.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'ResourceIntelligence',
                'ResourceAllocation',
                'ResourceWorkAttribution',
                'BillabilityReasoner',
                'BillabilityAssessment',
                'costRate',
                'valueCreated',
                'margin',
                'utilisation',
                'utilization',
                'capacity',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function logWork(
        User $user,
        Client $client,
        string $description,
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

                        'description' => $description,

                        'minutes' => $minutes,

                        'performed_at' => $performedAt,

                        'billing_hint' => 'billable',
                    ]
                );

        $response
            ->assertRedirect();
    }
}
