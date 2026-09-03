<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialAgreementTruthService;
use App\Domains\CommercialTruth\Services\CommercialAgreementAssertionService;
use App\Domains\CommercialTruth\Services\CommercialAgreementHumanReviewService;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAgreementHumanReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_read_only_and_exposes_establish_terms_when_no_agreement_exists(): void
    {
        $service =
            $this->service();

        $before = [
            'agreements' => CommercialAgreement::count(),

            'coverage' => CommercialAgreementCoverageReview::count(),
        ];

        $candidate =
            app(
                CommercialAgreementHumanReviewService::class
            )->preview(
                clientServiceId: $service->id,

                asOf: CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $after = [
            'agreements' => CommercialAgreement::count(),

            'coverage' => CommercialAgreementCoverageReview::count(),
        ];

        $this->assertContains(
            'establish_terms',
            $candidate
                ->availableDecisions
        );

        $this->assertSame(
            $before,
            $after
        );
    }

    public function test_establish_terms_atomically_creates_agreement_and_matching_terminal_coverage(): void
    {
        $service =
            $this->service();

        $reviewer =
            User::factory()->create();

        $result =
            app(
                CommercialAgreementHumanReviewService::class
            )->establishTerms(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-09-03'
                ),

                reviewedBy: $reviewer->id,

                source: 'owner_review',

                reason: 'Human confirmed current monthly retainer.'
            );

        $this->assertSame(
            1,
            CommercialAgreement::count()
        );

        $this->assertSame(
            1,
            CommercialAgreementCoverageReview::count()
        );

        $this->assertSame(
            $result[
                'agreement'
            ]->id,
            $result[
                'coverage_review'
            ]->commercial_agreement_id
        );

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            500.0,
            $truth[
                'contracted_monthly_value'
            ]
        );

        $this->assertSame(
            'reconciled',
            $truth[
                'contracted_value_status'
            ]
        );
    }

    public function test_explicit_zero_confirmed_terms_remains_distinct_from_no_current_contract(): void
    {
        $service =
            $this->service();

        $reviewer =
            User::factory()->create();

        app(
            CommercialAgreementHumanReviewService::class
        )->establishTerms(
            clientServiceId: $service->id,

            cadence: 'monthly',

            contractedAmountPence: 0,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-03'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Human confirmed explicit zero contractual amount.'
        );

        $agreement =
            CommercialAgreement::firstOrFail();

        $coverage =
            CommercialAgreementCoverageReview::firstOrFail();

        $this->assertSame(
            0,
            $agreement
                ->contracted_amount_pence
        );

        $this->assertSame(
            'confirmed_terms',
            $coverage->outcome
        );

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            0.0,
            $truth[
                'contracted_monthly_value'
            ]
        );
    }

    public function test_no_current_contract_is_terminal_without_creating_agreement(): void
    {
        $service =
            $this->service();

        $reviewer =
            User::factory()->create();

        app(
            CommercialAgreementHumanReviewService::class
        )->confirmNoCurrentContract(
            clientServiceId: $service->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-03'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Human confirmed there is no current contractual commitment.'
        );

        $this->assertSame(
            0,
            CommercialAgreement::count()
        );

        $this->assertSame(
            'no_current_contract',
            CommercialAgreementCoverageReview::firstOrFail()
                ->outcome
        );

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            0.0,
            $truth[
                'contracted_monthly_value'
            ]
        );

        $this->assertSame(
            'reconciled',
            $truth[
                'contracted_value_status'
            ]
        );
    }

    public function test_needs_more_evidence_remains_non_terminal_and_total_unknown(): void
    {
        $service =
            $this->service();

        $reviewer =
            User::factory()->create();

        app(
            CommercialAgreementHumanReviewService::class
        )->defer(
            clientServiceId: $service->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-03'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Contractual evidence is insufficient.'
        );

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertNull(
            $truth[
                'contracted_monthly_value'
            ]
        );

        $this->assertSame(
            'partially_established',
            $truth[
                'contracted_value_status'
            ]
        );
    }

    public function test_existing_current_agreement_can_be_explicitly_covered_without_creating_another_agreement(): void
    {
        $service =
            $this->service();

        $reviewer =
            User::factory()->create();

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $service->id,

                cadence: 'annual',

                contractedAmountPence: 120000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'Existing annual agreement.'
            );

        $candidate =
            app(
                CommercialAgreementHumanReviewService::class
            )->preview(
                clientServiceId: $service->id,

                asOf: CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertContains(
            'confirm_terms',
            $candidate
                ->availableDecisions
        );

        app(
            CommercialAgreementHumanReviewService::class
        )->confirmCurrentTerms(
            clientServiceId: $service->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-03'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Human confirmed current agreement closes coverage.'
        );

        $this->assertSame(
            1,
            CommercialAgreement::count()
        );

        $this->assertSame(
            $agreement->id,
            CommercialAgreementCoverageReview::firstOrFail()
                ->commercial_agreement_id
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
