<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialAgreementInferenceService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAgreementInferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosting_invoice_history_returns_read_only_agreement_candidate(): void
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

        $this->assertSame(
            0,
            CommercialAgreement::count()
        );

        $this->assertSame(
            0,
            CommercialAgreementEvidence::count()
        );

        $candidates =
            app(
                CommercialAgreementInferenceService::class
            )->inferHosting();

        $this->assertCount(
            1,
            $candidates
        );

        $candidate =
            $candidates->first();

        $this->assertSame(
            $client->id,
            $candidate->clientId
        );

        $this->assertSame(
            'hosting',
            $candidate->serviceType
        );

        $this->assertSame(
            'monthly',
            $candidate->cadence
        );

        $this->assertSame(
            75.0,
            $candidate->monthlyEquivalent
        );

        $this->assertSame(
            'invoice_history',
            $candidate->source
        );

        $this->assertCount(
            3,
            $candidate->evidence
        );

        /*
         * Critical contract:
         * inference never persists agreement truth.
         */
        $this->assertSame(
            0,
            CommercialAgreement::count()
        );

        $this->assertSame(
            0,
            CommercialAgreementEvidence::count()
        );
    }
}
