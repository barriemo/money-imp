<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialAgreementTruthService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementEvidence;
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
            )->summary();

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
                'recurring_monthly_equivalent'
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

    public function test_summary_counts_only_confirmed_persisted_agreements_as_contracted_value(): void
    {
        $confirmedClient =
            Client::factory()->create();

        CommercialAgreement::create([
            'client_id' => $confirmedClient->id,

            'service_type' => 'hosting',

            'service_key' => hash(
                'sha256',
                'confirmed-hosting'
            ),

            'cadence' => 'monthly',

            'status' => 'confirmed',

            'observed_value' => 75,

            'monthly_equivalent' => 75,

            'confidence' => 100,

            'source' => 'owner',

            'reason' => 'Human-confirmed contracted hosting value.',
        ]);

        $candidateClient =
            Client::factory()->create();

        CommercialAgreement::create([
            'client_id' => $candidateClient->id,

            'service_type' => 'retainer',

            'service_key' => hash(
                'sha256',
                'candidate-retainer'
            ),

            'cadence' => 'monthly',

            'status' => 'candidate',

            'observed_value' => 500,

            'monthly_equivalent' => 500,

            'confidence' => 90,

            'source' => 'invoice_history',

            'reason' => 'Inference only.',
        ]);

        $before =
            CommercialAgreement::count();

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary();

        $this->assertSame(
            2,
            $truth['agreement_count']
        );

        $this->assertSame(
            1,
            $truth['confirmed_count']
        );

        $this->assertSame(
            1,
            $truth['candidate_count']
        );

        $this->assertSame(
            1,
            $truth['monthly_count']
        );

        $this->assertSame(
            75.0,
            $truth[
                'recurring_monthly_equivalent'
            ]
        );

        $this->assertSame(
            75.0,
            $truth[
                'contracted_monthly_value'
            ]
        );

        $this->assertSame(
            'partially_reconciled',
            $truth[
                'contracted_value_status'
            ]
        );

        /*
         * Repeated reads remain pure.
         */
        app(
            CommercialAgreementTruthService::class
        )->summary();

        $this->assertSame(
            $before,
            CommercialAgreement::count()
        );

        $this->assertSame(
            0,
            CommercialAgreementEvidence::count()
        );
    }
}
