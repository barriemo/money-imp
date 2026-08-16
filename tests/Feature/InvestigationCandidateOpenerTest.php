<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use App\Domains\BusinessBrain\Investigation\Opening\InvestigationCandidateOpener;
use App\Domains\BusinessBrain\Investigation\Queue\InvestigationCandidateQueue;
use App\Domains\BusinessBrain\Investigation\Queue\InvestigationCandidateQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationCandidateOpenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_best_ready_candidate_can_be_opened_as_investigation(): void
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
                confidence: 90
            );

        $this->mock(
            InvestigationCandidateQueueService::class,
            function ($mock) use ($candidate): void {
                $mock->shouldReceive(
                    'current'
                )
                    ->once()
                    ->andReturn(
                        new InvestigationCandidateQueue(
                            readyNow: collect([
                                $candidate,
                            ]),
                            waitingForEvidence: collect(),
                            lowerPriority: collect(),
                            bestNext: $candidate
                        )
                    );
            }
        );

        $case =
            app(
                InvestigationCandidateOpener::class
            )->next();

        $this->assertNotNull(
            $case
        );

        $this->assertSame(
            "Burty's Timber",
            $case->subject_name
        );

        $this->assertSame(
            'client_ledger',
            $case->type
        );

        $this->assertSame(
            'open',
            $case->status
        );

        $this->assertDatabaseHas(
            'investigation_cases',
            [
                'subject_id' => 'burtys',
                'status' => 'open',
            ]
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'case_opened',
            ]
        );
    }

    public function test_no_ready_candidate_opens_nothing(): void
    {
        $this->mock(
            InvestigationCandidateQueueService::class,
            function ($mock): void {
                $mock->shouldReceive(
                    'current'
                )
                    ->once()
                    ->andReturn(
                        new InvestigationCandidateQueue(
                            readyNow: collect(),
                            waitingForEvidence: collect(),
                            lowerPriority: collect(),
                            bestNext: null
                        )
                    );
            }
        );

        $this->assertNull(
            app(
                InvestigationCandidateOpener::class
            )->next()
        );

        $this->assertDatabaseCount(
            'investigation_cases',
            0
        );
    }
}
