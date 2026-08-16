<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidateService;
use App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence\ClientLedgerRisk;
use App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence\ClientLedgerRiskService;
use App\Models\InvestigationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationCandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_priority_ledger_anomaly_becomes_investigation_candidate(): void
    {
        $this->mock(
            ClientLedgerRiskService::class,
            function ($mock): void {
                $mock->shouldReceive(
                    'current'
                )
                    ->once()
                    ->andReturn(
                        collect([
                            $this->risk(
                                clientId: 'walker',
                                clientName: 'Walker The Jeweller Ltd',
                                classification: 'high_confidence_anomaly',
                                priority: 95,
                                confidence: 90
                            ),
                        ])
                    );
            }
        );

        $candidate =
            app(
                InvestigationCandidateService::class
            )
                ->current()
                ->first();

        $this->assertNotNull(
            $candidate
        );

        $this->assertSame(
            'walker',
            $candidate->subjectId
        );

        $this->assertSame(
            95,
            $candidate->priority
        );

        $this->assertSame(
            'client_ledger',
            $candidate->type
        );
    }

    public function test_existing_active_investigation_suppresses_candidate(): void
    {
        InvestigationCase::create([
            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'peak',
            'subject_name' => 'Peak Renewables',
            'title' => 'Peak investigation',
            'status' => 'waiting',
            'confidence' => 70,
            'opened_at' => now(),
        ]);

        $this->mock(
            ClientLedgerRiskService::class,
            function ($mock): void {
                $mock->shouldReceive(
                    'current'
                )
                    ->once()
                    ->andReturn(
                        collect([
                            $this->risk(
                                clientId: 'peak',
                                clientName: 'Peak Renewables',
                                classification: 'high_confidence_anomaly',
                                priority: 95,
                                confidence: 90
                            ),
                        ])
                    );
            }
        );

        $this->assertTrue(
            app(
                InvestigationCandidateService::class
            )
                ->current()
                ->isEmpty()
        );
    }

    public function test_reconciled_ledger_does_not_become_candidate(): void
    {
        $this->mock(
            ClientLedgerRiskService::class,
            function ($mock): void {
                $mock->shouldReceive(
                    'current'
                )
                    ->once()
                    ->andReturn(
                        collect([
                            $this->risk(
                                clientId: 'reconciled',
                                clientName: 'Reconciled Client',
                                classification: 'ledger_reconciled',
                                priority: 0,
                                confidence: 95
                            ),
                        ])
                    );
            }
        );

        $this->assertTrue(
            app(
                InvestigationCandidateService::class
            )
                ->current()
                ->isEmpty()
        );
    }

    private function risk(
        string $clientId,
        string $clientName,
        string $classification,
        int $priority,
        int $confidence
    ): ClientLedgerRisk {
        return new ClientLedgerRisk(
            clientId: $clientId,
            clientName: $clientName,
            classification: $classification,
            difference: -5000,
            cashReceived: 1000,
            invoiceValue: 6000,
            priority: $priority,
            confidence: $confidence,
            reasons: [
                'Canonical cash received: £1,000.00.',
                'Invoice evidence in the visible period: £6,000.00.',
                'Ledger difference: -£5,000.00.',
            ],
            actions: [
                'Review the client invoice ledger.',
            ]
        );
    }
}
