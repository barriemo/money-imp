<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBriefService;
use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use App\Domains\BusinessBrain\Investigation\Queue\InvestigationCandidateQueue;
use App\Domains\BusinessBrain\Investigation\Queue\InvestigationCandidateQueueService;
use App\Models\BusinessExperience;
use App\Models\InvestigationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessBrainBriefServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_brief_summarises_current_brain_workload(): void
    {
        InvestigationCase::create([
            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'peak',
            'subject_name' => 'Peak',
            'title' => 'Peak investigation',
            'status' => 'waiting',
            'confidence' => 70,
            'opened_at' => now(),
        ]);

        InvestigationCase::create([
            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'closed-client',
            'subject_name' => 'Closed Client',
            'title' => 'Closed investigation',
            'status' => 'closed',
            'confidence' => 95,
            'opened_at' => now()->subDay(),
            'closed_at' => now(),
        ]);

        $source =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'experience-client',
                'subject_name' => 'Experience Client',
                'title' => 'Experience source',
                'status' => 'closed',
                'confidence' => 95,
                'opened_at' => now()->subMonth(),
                'closed_at' => now()->subMonth()->addHour(),
            ]);

        BusinessExperience::create([
            'source_investigation_case_id' => $source->id,
            'fingerprint' => hash(
                'sha256',
                'brain-brief-experience'
            ),
            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'experience-client',
            'subject_name' => 'Experience Client',
            'title' => 'Experience',
            'confidence' => 95,
            'importance' => 80,
            'experienced_at' => now()->subMonth(),
        ]);

        $this->mock(
            InvestigationCandidateQueueService::class,
            function ($mock): void {
                $mock->shouldReceive(
                    'current'
                )
                    ->once()
                    ->andReturn(
                        new InvestigationCandidateQueue(
                            readyNow: collect([
                                $this->candidate(
                                    'burtys',
                                    "Burty's Timber",
                                    48,
                                    90,
                                    -3960
                                ),
                            ]),

                            waitingForEvidence: collect([
                                $this->candidate(
                                    'walker',
                                    'Walker The Jeweller Ltd',
                                    70,
                                    45,
                                    -46720.89
                                ),
                            ]),

                            lowerPriority: collect(),

                            bestNext: $this->candidate(
                                'burtys',
                                "Burty's Timber",
                                48,
                                90,
                                -3960
                            )
                        )
                    );
            }
        );

        $brief =
            app(
                BusinessBrainBriefService::class
            )->current();

        $this->assertSame(
            1,
            $brief->activeInvestigationCount
        );

        $this->assertSame(
            1,
            $brief->waitingInvestigationCount
        );

        $this->assertSame(
            2,
            $brief->candidateCount
        );

        $this->assertSame(
            1,
            $brief->recentlyClosedCount
        );

        $this->assertSame(
            1,
            $brief->experienceCount
        );

        $this->assertSame(
            70,
            $brief->averageActiveConfidence
        );

        $this->assertSame(
            "Burty's Timber",
            $brief->highestConfidenceCandidate
                ?->subjectName
        );

        $this->assertSame(
            'Walker The Jeweller Ltd',
            $brief->highestImpactCandidate
                ?->subjectName
        );
    }

    private function candidate(
        string $id,
        string $name,
        int $priority,
        int $confidence,
        float $difference
    ): InvestigationCandidate {
        return new InvestigationCandidate(
            type: 'client_ledger',
            subjectType: 'client',
            subjectId: $id,
            subjectName: $name,
            title: 'Investigate ledger anomaly for '.$name,
            question: 'Why does the client ledger not reconcile?',
            classification: 'high_confidence_anomaly',
            priority: $priority,
            confidence: $confidence,
            metadata: [
                'ledger_difference' => $difference,
            ]
        );
    }
}
