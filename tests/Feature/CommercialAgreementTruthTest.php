<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialAgreementTruthService;
use App\Domains\CommercialTruth\Services\CommercialAgreementAssertionService;
use App\Domains\CommercialTruth\Services\CommercialAgreementCoverageReviewService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementEvidence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAgreementTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_does_not_infer_or_persist_contract_truth_from_invoice_history(): void
    {
        $client =
            Client::factory()->create();

        foreach ([
            '2026-05-31',
            '2026-06-30',
            '2026-07-31',
        ] as $date) {
            $invoice =
                AccountingInvoice::create([
                    'client_id' => $client->id,

                    'invoice_number' => 'HOST-'.$date,

                    'invoice_date' => $date,

                    'status' => 'paid',
                ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,

                'description' => 'Monthly Hosting, Security Updates & Backups',

                'quantity' => 1,

                'unit_price' => 75,

                'net_amount' => 75,
            ]);
        }

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            0,
            $truth['agreement_count']
        );

        $this->assertSame(
            0,
            $truth['candidate_count']
        );

        $this->assertSame(
            0,
            $truth['confirmed_count']
        );

        $this->assertSame(
            0.0,
            $truth[
                'confirmed_recurring_monthly_equivalent'
            ]
        );

        $this->assertNull(
            $truth[
                'contracted_monthly_value'
            ]
        );

        $this->assertSame(
            'not_established',
            $truth[
                'contracted_value_status'
            ]
        );

        $this->assertSame(
            0,
            CommercialAgreement::count()
        );

        $this->assertSame(
            0,
            CommercialAgreementEvidence::count()
        );
    }

    public function test_confirmed_assertion_provides_known_subtotal_without_inventing_complete_total(): void
    {
        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Website Hosting',

                'type' => 'service',

                'status' => 'active',
            ]);

        $reviewer =
            User::factory()->create();

        app(
            CommercialAgreementAssertionService::class
        )->confirm(
            clientServiceId: $service->id,

            cadence: 'monthly',

            contractedAmountPence: 7500,

            effectiveFrom: CarbonImmutable::parse(
                '2026-01-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner',

            reason: 'Human-confirmed hosting terms.'
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
            1,
            $truth['confirmed_count']
        );

        $this->assertSame(
            75.0,
            $truth[
                'confirmed_recurring_monthly_equivalent'
            ]
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

        /*
         * Repeated reads remain pure.
         */
        $before =
            CommercialAgreement::count();

        app(
            CommercialAgreementTruthService::class
        )->summary(
            CarbonImmutable::parse(
                '2026-09-03'
            )
        );

        $this->assertSame(
            $before,
            CommercialAgreement::count()
        );
    }

    public function test_future_supersession_does_not_hide_current_terms_before_effective_date(): void
    {
        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Monthly Support',

                'type' => 'service',

                'status' => 'active',
            ]);

        $reviewer =
            User::factory()->create();

        $writer =
            app(
                CommercialAgreementAssertionService::class
            );

        $first =
            $writer->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'Original monthly terms.'
            );

        $writer->supersede(
            commercialAgreementId: $first->id,

            cadence: 'monthly',

            contractedAmountPence: 75000,

            effectiveFrom: CarbonImmutable::parse(
                '2026-10-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'email',

            reason: 'Future price change agreed.'
        );

        $september =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            500.0,
            $september[
                'confirmed_recurring_monthly_equivalent'
            ]
        );

        $this->assertSame(
            1,
            $september[
                'future_assertion_count'
            ]
        );

        $october =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-10-02'
                )
            );

        $this->assertSame(
            750.0,
            $october[
                'confirmed_recurring_monthly_equivalent'
            ]
        );

        $this->assertSame(
            0,
            $october[
                'future_assertion_count'
            ]
        );
    }

    public function test_partial_terminal_coverage_keeps_business_wide_contracted_total_unknown(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $contracted =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Monthly Retainer',

                'type' => 'service',

                'status' => 'active',
            ]);

        ClientService::create([
            'client_id' => $client->id,

            'name' => 'Ad Hoc Support',

            'type' => 'service',

            'status' => 'active',
        ]);

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $contracted->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'Confirmed monthly terms.'
            );

        app(
            CommercialAgreementCoverageReviewService::class
        )->confirmTerms(
            clientServiceId: $contracted->id,

            commercialAgreementId: $agreement->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'One of two active services reviewed.'
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
                'confirmed_recurring_monthly_equivalent'
            ]
        );

        $this->assertSame(
            500.0,
            $truth[
                'covered_confirmed_recurring_monthly_equivalent'
            ]
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

        $this->assertSame(
            2,
            $truth[
                'coverage_scope_count'
            ]
        );

        $this->assertSame(
            1,
            $truth[
                'coverage_terminal_count'
            ]
        );

        $this->assertSame(
            1,
            $truth[
                'coverage_unresolved_count'
            ]
        );

        $this->assertFalse(
            $truth[
                'coverage_complete'
            ]
        );
    }

    public function test_complete_coverage_unlocks_exact_contracted_monthly_total(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $contracted =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Monthly Retainer',

                'type' => 'service',

                'status' => 'active',
            ]);

        $noContract =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Ad Hoc Support',

                'type' => 'service',

                'status' => 'active',
            ]);

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $contracted->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'Confirmed monthly terms.'
            );

        $coverage =
            app(
                CommercialAgreementCoverageReviewService::class
            );

        $coverage->confirmTerms(
            clientServiceId: $contracted->id,

            commercialAgreementId: $agreement->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Contract terms reviewed.'
        );

        $coverage->confirmNoCurrentContract(
            clientServiceId: $noContract->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'No current contractual commitment.'
        );

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertTrue(
            $truth[
                'coverage_complete'
            ]
        );

        $this->assertSame(
            0,
            $truth[
                'coverage_unresolved_count'
            ]
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

    public function test_complete_all_no_contract_coverage_produces_legitimate_zero_not_unknown(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Ad Hoc Support',

                'type' => 'service',

                'status' => 'active',
            ]);

        app(
            CommercialAgreementCoverageReviewService::class
        )->confirmNoCurrentContract(
            clientServiceId: $service->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Explicitly reviewed: no current contractual commitment.'
        );

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertTrue(
            $truth[
                'coverage_complete'
            ]
        );

        $this->assertSame(
            1,
            $truth[
                'coverage_no_current_contract_count'
            ]
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

    public function test_stale_terminal_coverage_returns_total_to_unknown_until_re_reviewed(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Monthly Support',

                'type' => 'service',

                'status' => 'active',
            ]);

        $agreements =
            app(
                CommercialAgreementAssertionService::class
            );

        $first =
            $agreements->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'September terms.'
            );

        app(
            CommercialAgreementCoverageReviewService::class
        )->confirmTerms(
            clientServiceId: $service->id,

            commercialAgreementId: $first->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'September terms reviewed.'
        );

        $agreements->supersede(
            commercialAgreementId: $first->id,

            cadence: 'monthly',

            contractedAmountPence: 75000,

            effectiveFrom: CarbonImmutable::parse(
                '2026-10-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'email',

            reason: 'October price change.'
        );

        $september =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            500.0,
            $september[
                'contracted_monthly_value'
            ]
        );

        $this->assertSame(
            'reconciled',
            $september[
                'contracted_value_status'
            ]
        );

        $october =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-10-02'
                )
            );

        $this->assertSame(
            750.0,
            $october[
                'confirmed_recurring_monthly_equivalent'
            ]
        );

        $this->assertNull(
            $october[
                'contracted_monthly_value'
            ]
        );

        $this->assertSame(
            1,
            $october[
                'coverage_stale_terminal_review_count'
            ]
        );

        $this->assertSame(
            1,
            $october[
                'coverage_unresolved_count'
            ]
        );

        $this->assertSame(
            'partially_established',
            $october[
                'contracted_value_status'
            ]
        );
    }

    public function test_matching_future_coverage_review_preserves_as_of_complete_totals(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Monthly Support',

                'type' => 'service',

                'status' => 'active',
            ]);

        $agreements =
            app(
                CommercialAgreementAssertionService::class
            );

        $first =
            $agreements->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'September terms.'
            );

        $future =
            $agreements->supersede(
                commercialAgreementId: $first->id,

                cadence: 'monthly',

                contractedAmountPence: 75000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-10-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'email',

                reason: 'October terms.'
            );

        $coverage =
            app(
                CommercialAgreementCoverageReviewService::class
            );

        $coverage->confirmTerms(
            clientServiceId: $service->id,

            commercialAgreementId: $first->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'September terms reviewed.'
        );

        $coverage->confirmTerms(
            clientServiceId: $service->id,

            commercialAgreementId: $future->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-10-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'October terms reviewed.'
        );

        $september =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            500.0,
            $september[
                'contracted_monthly_value'
            ]
        );

        $this->assertSame(
            'reconciled',
            $september[
                'contracted_value_status'
            ]
        );

        $october =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-10-02'
                )
            );

        $this->assertSame(
            750.0,
            $october[
                'contracted_monthly_value'
            ]
        );

        $this->assertSame(
            'reconciled',
            $october[
                'contracted_value_status'
            ]
        );

        $this->assertSame(
            0,
            $october[
                'coverage_stale_terminal_review_count'
            ]
        );
    }
}
