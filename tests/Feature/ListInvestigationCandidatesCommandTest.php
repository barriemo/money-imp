<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListInvestigationCandidatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_command_displays_ranked_investigation_work(): void
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
                            new InvestigationCandidate(
                                type: 'client_ledger',
                                subjectType: 'client',
                                subjectId: 'walker',
                                subjectName: 'Walker The Jeweller Ltd',
                                title: 'Investigate ledger anomaly for Walker The Jeweller Ltd',
                                question: 'Why does the client ledger not reconcile?',
                                classification: 'high_confidence_anomaly',
                                priority: 95,
                                confidence: 90,
                                reasons: [
                                    'Ledger difference: -£5,000.00.',
                                ],
                                actions: [
                                    'Review the client invoice ledger.',
                                ]
                            ),
                        ])
                    );
            }
        );

        $this->artisan(
            'business:investigations:candidates'
        )
            ->expectsOutputToContain(
                'Walker The Jeweller Ltd'
            )
            ->expectsOutputToContain(
                'Priority: 95'
            )
            ->expectsOutputToContain(
                'Confidence: 90%'
            )
            ->expectsOutputToContain(
                'Ledger difference: -£5,000.00.'
            )
            ->assertSuccessful();
    }

    public function test_candidate_command_has_clear_empty_state(): void
    {
        $this->mock(
            InvestigationCandidateService::class,
            function ($mock): void {
                $mock->shouldReceive(
                    'current'
                )
                    ->once()
                    ->andReturn(
                        collect()
                    );
            }
        );

        $this->artisan(
            'business:investigations:candidates'
        )
            ->expectsOutputToContain(
                'no new investigation candidates'
            )
            ->assertSuccessful();
    }
}
