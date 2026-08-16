<?php

namespace Tests\Feature;

use App\Models\InvestigationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillBusinessExperiencesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_historical_investigation_can_be_backfilled_into_experience(): void
    {
        $case =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'historical-client',
                'subject_name' => 'Historical Client',
                'title' => 'Historical ledger investigation',
                'question' => 'Why did the ledger not reconcile?',
                'status' => 'closed',
                'confidence' => 95,
                'current_hypothesis' => 'Historical bank evidence was incomplete.',
                'verdict' => 'Additional historical evidence resolved the apparent mismatch.',
                'opened_at' => now()->subMonth(),
                'closed_at' => now()->subMonth()->addHour(),
            ]);

        $this->artisan(
            'business:experiences:backfill'
        )
            ->expectsOutputToContain(
                'Historical Client'
            )
            ->assertSuccessful();

        $this->assertDatabaseHas(
            'business_experiences',
            [
                'source_investigation_case_id' => $case->id,
                'subject_name' => 'Historical Client',
                'confidence' => 95,
            ]
        );
    }

    public function test_backfill_is_idempotent(): void
    {
        $case =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'historical-client',
                'subject_name' => 'Historical Client',
                'title' => 'Historical ledger investigation',
                'status' => 'closed',
                'confidence' => 90,
                'verdict' => 'Historical evidence resolved the issue.',
                'opened_at' => now()->subMonth(),
                'closed_at' => now()->subMonth()->addHour(),
            ]);

        $this->artisan(
            'business:experiences:backfill'
        )->assertSuccessful();

        $this->artisan(
            'business:experiences:backfill'
        )->assertSuccessful();

        $this->assertDatabaseCount(
            'business_experiences',
            1
        );

        $this->assertDatabaseHas(
            'business_experiences',
            [
                'source_investigation_case_id' => $case->id,
            ]
        );
    }

    public function test_dry_run_does_not_create_experience(): void
    {
        InvestigationCase::create([
            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'historical-client',
            'subject_name' => 'Historical Client',
            'title' => 'Historical ledger investigation',
            'status' => 'closed',
            'confidence' => 90,
            'opened_at' => now()->subMonth(),
            'closed_at' => now()->subMonth()->addHour(),
        ]);

        $this->artisan(
            'business:experiences:backfill',
            [
                '--dry-run' => true,
            ]
        )
            ->expectsOutputToContain(
                'Dry run only'
            )
            ->assertSuccessful();

        $this->assertDatabaseCount(
            'business_experiences',
            0
        );
    }
}
