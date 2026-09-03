<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialAgreementTruthService;
use App\Domains\CommercialTruth\Services\CommercialAgreementAssertionService;
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
}
