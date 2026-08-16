<?php

namespace Tests\Feature;

use App\Models\BusinessExperience;
use App\Models\InvestigationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListBusinessExperiencesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_experiences_command_lists_captured_experience(): void
    {
        $case =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'historical-client',
                'subject_name' => 'Historical Client',
                'title' => 'Historical ledger investigation',
                'status' => 'closed',
                'confidence' => 95,
                'current_hypothesis' => 'Historical bank evidence was incomplete.',
                'verdict' => 'Historical bank evidence resolved the apparent ledger difference.',
                'opened_at' => now()->subDay(),
                'closed_at' => now(),
            ]);

        BusinessExperience::create([
            'source_investigation_case_id' => $case->id,
            'fingerprint' => hash(
                'sha256',
                'experience-command-test'
            ),
            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'historical-client',
            'subject_name' => 'Historical Client',
            'title' => 'Historical ledger investigation',
            'outcome' => 'Historical bank evidence resolved the apparent ledger difference.',
            'confidence' => 95,
            'importance' => 80,
            'hypothesis' => 'Historical bank evidence was incomplete.',
            'lessons' => [],
            'evidence_summary' => [],
            'experienced_at' => now(),
        ]);

        $this->artisan(
            'business:experiences'
        )
            ->expectsOutputToContain(
                'Historical Client'
            )
            ->expectsOutputToContain(
                'client_ledger'
            )
            ->expectsOutputToContain(
                '95%'
            )
            ->expectsOutputToContain(
                'Historical bank evidence resolved'
            )
            ->assertSuccessful();
    }

    public function test_business_experiences_command_has_clear_empty_state(): void
    {
        $this->artisan(
            'business:experiences'
        )
            ->expectsOutputToContain(
                'has not captured any matching business experiences yet'
            )
            ->assertSuccessful();
    }
}
