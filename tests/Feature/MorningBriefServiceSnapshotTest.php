<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\MorningBrief\History\MorningBriefSnapshotRepository;
use App\Domains\BusinessBrain\MorningBrief\Services\MorningBriefService;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use Tests\TestCase;

class MorningBriefServiceSnapshotTest extends TestCase
{
    public function test_building_morning_brief_stores_snapshot(): void
    {
        $service =
            app(
                MorningBriefService::class
            );

        $service->build(
            new AttentionContext(
                client: 'Walker',

                recovery: new RecoveryOpportunitySummary(
                    clientId: 'Walker',

                    opportunityCount: 1,

                    totalValue: 5000,

                    highestValue: 5000,

                    confidence: 95
                )
            )
        );

        $snapshot =
            app(
                MorningBriefSnapshotRepository::class
            )->latest();

        $this->assertNotNull(
            $snapshot
        );

        $this->assertSame(
            'Walker',
            $snapshot->client
        );

        $this->assertSame(
            1,
            $snapshot->signalCount
        );

        $this->assertSame(
            'recovery',
            $snapshot->signals->first()->type
        );
    }
}
