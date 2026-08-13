<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\MorningBrief\History\MorningBriefSnapshot;
use App\Domains\BusinessBrain\MorningBrief\History\MorningBriefSnapshotRepository;
use Tests\TestCase;

class MorningBriefSnapshotRepositoryTest extends TestCase
{
    public function test_snapshot_can_be_stored_and_retrieved(): void
    {
        $repository =
            app(
                MorningBriefSnapshotRepository::class
            );

        $snapshot =
            new MorningBriefSnapshot(
                client: 'Walker',

                signalCount: 2,

                signals: collect([
                    [
                        'type' => 'vat_exposure',
                        'value' => 30000,
                    ],
                ]),

                generatedAt: now()
            );

        $repository->store(
            $snapshot
        );

        $latest =
            $repository->latest();

        $this->assertNotNull(
            $latest
        );

        $this->assertSame(
            'Walker',
            $latest->client
        );

        $this->assertSame(
            2,
            $latest->signalCount
        );
    }
}
