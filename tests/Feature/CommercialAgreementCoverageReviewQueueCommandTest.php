<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\CommercialAgreementEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CommercialAgreementCoverageReviewQueueCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_displays_ranked_read_only_contract_review_work(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Command Client',
            ]);

        ClientService::create([
            'client_id' => $client->id,

            'name' => 'Annual Domain Renewal',

            'type' => 'service',

            'status' => 'active',
        ]);

        $before = [
            'coverage_reviews' => CommercialAgreementCoverageReview::count(),

            'agreements' => CommercialAgreement::count(),

            'evidence' => CommercialAgreementEvidence::count(),
        ];

        $exitCode =
            Artisan::call(
                'money:contract-review-queue',
                [
                    '--as-of' => '2026-09-03',

                    '--limit' => 10,
                ]
            );

        $output =
            Artisan::output();

        $after = [
            'coverage_reviews' => CommercialAgreementCoverageReview::count(),

            'agreements' => CommercialAgreement::count(),

            'evidence' => CommercialAgreementEvidence::count(),
        ];

        $this->assertSame(
            0,
            $exitCode
        );

        $this->assertStringContainsString(
            'Contract Coverage Review Queue',
            $output
        );

        $this->assertStringContainsString(
            '1 unresolved of 1 in scope',
            $output
        );

        $this->assertStringContainsString(
            'Command Client',
            $output
        );

        $this->assertStringContainsString(
            'Annual Domain Renewal',
            $output
        );

        $this->assertStringContainsString(
            'Coverage: unreviewed',
            $output
        );

        $this->assertStringContainsString(
            'Observed billing: no_observed_billing',
            $output
        );

        $this->assertStringContainsString(
            'Available decisions: establish_terms, no_current_contract, needs_more_evidence',
            $output
        );

        /*
         * Merely displaying the human review queue must never
         * manufacture contractual or coverage truth.
         */
        $this->assertSame(
            $before,
            $after
        );
    }
}
