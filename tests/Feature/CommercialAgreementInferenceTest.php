<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialAgreementInferenceService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAgreementInferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_hosting_invoices_create_commercial_agreement(): void
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

                    'status' => 'sent',
                ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,

                'description' => 'Monthly Hosting, Security Updates & Backups',

                'quantity' => 1,

                'unit_price' => 75,

                'net_amount' => 75,
            ]);
        }

        $agreements = app(
            CommercialAgreementInferenceService::class
        )->inferHosting();

        $this->assertCount(
            1,
            $agreements
        );

        $agreement =
            $agreements->first();

        $this->assertSame(
            'hosting',
            $agreement->service_type
        );

        $this->assertSame(
            'monthly',
            $agreement->cadence
        );

        $this->assertSame(
            '75.00',
            $agreement->monthly_equivalent
        );

        $this->assertCount(
            3,
            $agreement->evidence
        );
    }
}
