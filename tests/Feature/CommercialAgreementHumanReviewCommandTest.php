<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CommercialAgreementHumanReviewCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_dry_run_by_default(): void
    {
        $service =
            $this->service();

        $exitCode =
            Artisan::call(
                'money:contract-review',
                [
                    'clientServiceId' => $service->id,

                    'decision' => 'establish_terms',

                    '--effective-from' => '2026-09-03',
                ]
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exitCode
        );

        $this->assertStringContainsString(
            'DRY RUN ONLY',
            $output
        );

        $this->assertStringContainsString(
            'Human Review Client',
            $output
        );

        $this->assertStringContainsString(
            'Monthly Retainer',
            $output
        );

        $this->assertSame(
            0,
            CommercialAgreement::count()
        );

        $this->assertSame(
            0,
            CommercialAgreementCoverageReview::count()
        );
    }

    public function test_execute_establish_terms_requires_explicit_human_inputs_and_persists_both_assertions(): void
    {
        $service =
            $this->service();

        $reviewer =
            User::factory()->create([
                'name' => 'Contract Reviewer',

                'email' => 'reviewer@example.test',
            ]);

        $exitCode =
            Artisan::call(
                'money:contract-review',
                [
                    'clientServiceId' => $service->id,

                    'decision' => 'establish_terms',

                    '--effective-from' => '2026-09-03',

                    '--reviewer-email' => $reviewer->email,

                    '--source' => 'owner_review',

                    '--reason' => 'Human confirmed exact contractual terms.',

                    '--cadence' => 'monthly',

                    '--amount-pence' => '50000',

                    '--execute' => true,
                ]
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exitCode
        );

        $this->assertStringContainsString(
            'Human contract review persisted.',
            $output
        );

        $this->assertSame(
            1,
            CommercialAgreement::count()
        );

        $this->assertSame(
            1,
            CommercialAgreementCoverageReview::count()
        );
    }

    public function test_execute_without_effective_date_fails_without_writing_truth(): void
    {
        $service =
            $this->service();

        $reviewer =
            User::factory()->create([
                'email' => 'reviewer@example.test',
            ]);

        $exitCode =
            Artisan::call(
                'money:contract-review',
                [
                    'clientServiceId' => $service->id,

                    'decision' => 'no_current_contract',

                    '--reviewer-email' => $reviewer->email,

                    '--source' => 'owner_review',

                    '--reason' => 'Human review.',

                    '--execute' => true,
                ]
            );

        $this->assertSame(
            1,
            $exitCode
        );

        $this->assertSame(
            0,
            CommercialAgreement::count()
        );

        $this->assertSame(
            0,
            CommercialAgreementCoverageReview::count()
        );
    }

    private function service(): ClientService
    {
        $client =
            Client::factory()->create([
                'name' => 'Human Review Client',
            ]);

        return ClientService::create([
            'client_id' => $client->id,

            'name' => 'Monthly Retainer',

            'type' => 'service',

            'status' => 'active',
        ]);
    }
}
