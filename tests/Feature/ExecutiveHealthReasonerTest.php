<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CommercialAgreementCoverageReviewService;
use App\Domains\Executive\ExecutiveHealthReasoner;
use App\Domains\Executive\ExecutiveQuestion;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveHealthReasonerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_contracted_value_is_not_reported_as_zero(): void
    {
        $answer =
            app(
                ExecutiveHealthReasoner::class
            )->answer(
                ExecutiveQuestion::canKeepLightsOn()
            );

        $this->assertArrayHasKey(
            'recurring_monthly_equivalent',
            $answer->metrics
        );

        /*
         * Unknown contracted value must remain unknown.
         *
         * It must never be coerced to zero merely because
         * contracted commercial truth is not established.
         */
        $this->assertNull(
            $answer->metrics[
                'recurring_monthly_equivalent'
            ]
        );

        $this->assertContains(
            'Contracted commercial terms are not yet established.',
            $answer->missingEvidence
        );
    }

    public function test_complete_explicit_zero_contract_coverage_is_reported_as_zero_not_unknown(): void
    {
        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Ad Hoc Support',

                'type' => 'service',

                'status' => 'active',
            ]);

        $reviewer =
            User::factory()->create();

        app(
            CommercialAgreementCoverageReviewService::class
        )->confirmNoCurrentContract(
            clientServiceId: $service->id,

            effectiveFrom: CarbonImmutable::parse(
                '2020-01-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Explicit complete zero contracted coverage.'
        );

        $answer =
            app(
                ExecutiveHealthReasoner::class
            )->answer(
                ExecutiveQuestion::canKeepLightsOn()
            );

        $this->assertArrayHasKey(
            'recurring_monthly_equivalent',
            $answer->metrics
        );

        $this->assertSame(
            0.0,
            $answer->metrics[
                'recurring_monthly_equivalent'
            ]
        );

        $this->assertNotContains(
            'Contracted commercial terms are not yet established.',
            $answer->missingEvidence
        );

        $this->assertNotContains(
            'Some contracted commercial terms are confirmed, but contracted-truth coverage is not yet complete.',
            $answer->missingEvidence
        );
    }

    public function test_business_viability_question_returns_supported_answer(): void
    {
        $answer = app(
            ExecutiveHealthReasoner::class
        )->answer(
            ExecutiveQuestion::canKeepLightsOn()
        );

        $this->assertSame(
            'can_keep_lights_on',
            $answer->questionType
        );

        $this->assertContains(
            $answer->assessment,
            [
                'YES',
                'NO',
                'INCOMPLETE',
            ]
        );

        $this->assertGreaterThanOrEqual(
            0,
            $answer->confidence
        );

        $this->assertLessThanOrEqual(
            100,
            $answer->confidence
        );
    }
}
