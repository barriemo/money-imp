<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Observations\BusinessObservation;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationChangeDetector;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationSnapshot;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessObservationPersistenceChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_persisted_observations_can_detect_improvement(): void
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

                        title: 'Client has overdue money requiring attention',

                        message: '£10,000.00 is overdue.',

                        priority: 95,

                        clientId: 'client-1',

                        client: 'Test Client',

                        value: 10000,

                        confidence: 100
                    ),
                ]),

                generatedAt: now()
                    ->subDay()
            )
        );

        $previous =
            $repository->latest();

        $current =
            new BusinessObservationSnapshot(
                observations: collect([
                    new BusinessObservation(
                        type: 'collections',

                        title: 'Client has overdue money requiring attention',

                        message: '£5,000.00 is overdue.',

                        priority: 85,

                        clientId: 'client-1',

                        client: 'Test Client',

                        value: 5000,

                        confidence: 100
                    ),
                ]),

                generatedAt: now()
            );

        $changes =
            app(
                BusinessObservationChangeDetector::class
            )->compare(
                $previous,
                $current
            );

        $this->assertCount(
            1,
            $changes
        );

        $this->assertSame(
            'improved',
            $changes->first()->type
        );

        $this->assertSame(
            10000.0,
            $changes->first()
                ->previous
                ->value
        );

        $this->assertSame(
            5000.0,
            $changes->first()
                ->observation
                ->value
        );
    }
}
