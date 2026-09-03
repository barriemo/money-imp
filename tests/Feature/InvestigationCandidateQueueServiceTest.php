<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidateService;
use App\Domains\BusinessBrain\Investigation\Queue\InvestigationCandidateBucket;
use App\Domains\BusinessBrain\Investigation\Queue\InvestigationCandidateQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationCandidateQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidates_are_triaged_into_actionability_buckets(): void
    {
        $this->mock(
            InvestigationCandidateService::class,
            function ($mock): void {
                $mock->shouldReceive(
                    'current'
                )
                    ->once()
                    ->andReturn(
                        collect([
                            $this->candidate(
                                id: 'burtys',
                                name: "Burty's Timber",
                                classification: 'high_confidence_anomaly',
                                priority: 48,
                                confidence: 90
                            ),

                            $this->candidate(
                                id: 'walker',
                                name: 'Walker The Jeweller Ltd',
                                classification: 'historical_evidence_incomplete',
                                priority: 70,
                                confidence: 45
                            ),

                            $this->candidate(
                                id: 'malcolm',
                                name: 'Malcolm, Ogilvie & Company Limited',
                                classification: 'cash_without_invoice_evidence',
                                priority: 55,
                                confidence: 60
                            ),

                            $this->candidate(
                                id: 'small',
                                name: 'Small Difference Ltd',
                                classification: 'other_anomaly',
                                priority: 20,
                                confidence: 60
                            ),
                        ])
                    );
            }
        );

        $queue =
            app(
                InvestigationCandidateQueueService::class
            )->current();

        $this->assertCount(
            1,
            $queue->readyNow
        );

        $this->assertCount(
            2,
            $queue->waitingForEvidence
        );

        $this->assertCount(
            1,
            $queue->lowerPriority
        );

        $this->assertSame(
            "Burty's Timber",
            $queue->bestNext
                ?->subjectName
        );

        $this->assertSame(
            4,
            $queue->total()
        );
    }

    public function test_high_confidence_anomaly_is_ready_now(): void
    {
        $service =
            app(
                InvestigationCandidateQueueService::class
            );

        $this->assertSame(
            InvestigationCandidateBucket::ReadyNow,
            $service->bucket(
                $this->candidate(
                    id: 'burtys',
                    name: "Burty's Timber",
                    classification: 'high_confidence_anomaly',
                    priority: 48,
                    confidence: 90
                )
            )
        );
    }

    public function test_incomplete_history_waits_for_evidence(): void
    {
        $service =
            app(
                InvestigationCandidateQueueService::class
            );

        $this->assertSame(
            InvestigationCandidateBucket::WaitingForEvidence,
            $service->bucket(
                $this->candidate(
                    id: 'walker',
                    name: 'Walker',
                    classification: 'historical_evidence_incomplete',
                    priority: 70,
                    confidence: 45
                )
            )
        );
    }

    public function test_invoice_balance_without_canonical_payment_is_ready_now(): void
    {
        $service =
            app(
                InvestigationCandidateQueueService::class
            );

        $this->assertSame(
            InvestigationCandidateBucket::ReadyNow,
            $service->bucket(
                $this->candidate(
                    id: 'vf',
                    name: 'VF Electrical Services Ltd',
                    classification: 'invoice_balance_without_canonical_payment_evidence',
                    priority: 54,
                    confidence: 80
                )
            )
        );
    }

    public function test_small_invoice_only_balance_is_not_promoted_to_ready_now(): void
    {
        $service =
            app(
                InvestigationCandidateQueueService::class
            );

        $this->assertSame(
            InvestigationCandidateBucket::LowerPriority,
            $service->bucket(
                $this->candidate(
                    id: 'small-debtor',
                    name: 'Small Debtor Ltd',
                    classification: 'invoice_balance_without_canonical_payment_evidence',
                    priority: 49,
                    confidence: 80
                )
            )
        );
    }

    public function test_accounting_paid_without_canonical_payment_waits_for_evidence(): void
    {
        $service =
            app(
                InvestigationCandidateQueueService::class
            );

        $this->assertSame(
            InvestigationCandidateBucket::WaitingForEvidence,
            $service->bucket(
                $this->candidate(
                    id: 'paid',
                    name: 'Accounting Paid Client',
                    classification: 'accounting_paid_without_canonical_payment_evidence',
                    priority: 25,
                    confidence: 65
                )
            )
        );
    }

    private function candidate(
        string $id,
        string $name,
        string $classification,
        int $priority,
        int $confidence
    ): InvestigationCandidate {
        return new InvestigationCandidate(
            type: 'client_ledger',
            subjectType: 'client',
            subjectId: $id,
            subjectName: $name,
            title: 'Investigate ledger anomaly for '.$name,
            question: 'Why does the client ledger not reconcile?',
            classification: $classification,
            priority: $priority,
            confidence: $confidence,
            metadata: [
                'ledger_difference' => -5000,
            ]
        );
    }
}
