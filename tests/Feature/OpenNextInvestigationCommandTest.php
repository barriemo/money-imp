<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Opening\InvestigationCandidateOpener;
use App\Models\InvestigationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenNextInvestigationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_opens_best_ready_investigation(): void
    {
        $case =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'burtys',
                'subject_name' => "Burty's Timber",
                'title' => "Investigate ledger anomaly for Burty's Timber",
                'question' => 'Why does the client ledger not reconcile?',
                'status' => 'open',
                'confidence' => 0,
                'opened_at' => now(),
            ]);

        $this->mock(
            InvestigationCandidateOpener::class,
            function ($mock) use ($case): void {
                $mock->shouldReceive(
                    'next'
                )
                    ->once()
                    ->andReturn(
                        $case
                    );
            }
        );

        $this->artisan(
            'business:investigations:open-next'
        )
            ->expectsOutputToContain(
                'Investigation opened'
            )
            ->expectsOutputToContain(
                "Burty's Timber"
            )
            ->expectsOutputToContain(
                'OPEN'
            )
            ->assertSuccessful();
    }

    public function test_command_handles_empty_ready_queue(): void
    {
        $this->mock(
            InvestigationCandidateOpener::class,
            function ($mock): void {
                $mock->shouldReceive(
                    'next'
                )
                    ->once()
                    ->andReturn(
                        null
                    );
            }
        );

        $this->artisan(
            'business:investigations:open-next'
        )
            ->expectsOutputToContain(
                'no investigation candidates ready to open'
            )
            ->assertSuccessful();
    }
}
