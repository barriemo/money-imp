<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBrief;
use App\Domains\BusinessBrain\Briefing\BusinessBrainBriefService;
use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessBrainCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_brain_command_presents_current_brief(): void
    {
        $candidate =
            new InvestigationCandidate(
                type: 'client_ledger',
                subjectType: 'client',
                subjectId: 'burtys',
                subjectName: "Burty's Timber",
                title: "Investigate ledger anomaly for Burty's Timber",
                question: 'Why does the client ledger not reconcile?',
                classification: 'high_confidence_anomaly',
                priority: 48,
                confidence: 90,
                metadata: [
                    'ledger_difference' => -3960,
                ]
            );

        $this->mock(
            BusinessBrainBriefService::class,
            function ($mock) use ($candidate): void {
                $mock->shouldReceive(
                    'current'
                )
                    ->once()
                    ->andReturn(
                        new BusinessBrainBrief(
                            activeInvestigationCount: 1,
                            waitingInvestigationCount: 1,
                            candidateCount: 10,
                            readyNowCount: 5,
                            waitingForEvidenceCandidateCount: 3,
                            lowerPriorityCandidateCount: 2,
                            recentlyClosedCount: 0,
                            experienceCount: 0,
                            averageActiveConfidence: 70,
                            highestConfidenceCandidate: $candidate,
                            highestImpactCandidate: $candidate,
                            bestNextCandidate: $candidate
                        )
                    );
            }
        );

        $this->artisan(
            'business:brain'
        )
            ->expectsOutputToContain(
                'Money Imp Business Brain'
            )
            ->expectsOutputToContain(
                'Active investigations: 1'
            )
            ->expectsOutputToContain(
                'New investigation candidates: 10'
            )
            ->expectsOutputToContain(
                "Burty's Timber"
            )
            ->expectsOutputToContain(
                '90%'
            )
            ->assertSuccessful();
    }
}
