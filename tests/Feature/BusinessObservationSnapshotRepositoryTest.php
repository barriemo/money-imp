<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Observations\BusinessObservation;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationSnapshot;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessObservationSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_observation_snapshot_persists_and_can_be_reloaded(): void
    {
        $repository =
            app(
                BusinessObservationSnapshotRepository::class
            );

        $repository->store(
            new BusinessObservationSnapshot(
                observations: collect([
                    new BusinessObservation(
                        type: 'collections',

                        title: 'Client has overdue money',

                        message: '£5000 is overdue.',

                        priority: 90,

                        clientId: 'client-1',

                        client: 'Test Client',

                        value: 5000,

                        confidence: 100
                    ),
                ]),

                generatedAt: now()
            )
        );

        $latest =
            app(
                BusinessObservationSnapshotRepository::class
            )->latest();

        $this->assertNotNull(
            $latest
        );

        $this->assertCount(
            1,
            $latest->observations
        );

        $this->assertSame(
            'Test Client',
            $latest->observations
                ->first()
                ->client
        );
    }
}
