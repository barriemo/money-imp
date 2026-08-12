<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialAgreementTruthService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAgreementTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_truth_summarises_recurring_hosting_value(): void
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

        $truth = app(
            CommercialAgreementTruthService::class
        )->summary();

        $this->assertSame(
            1,
            $truth['agreement_count']
        );

        $this->assertSame(
            1,
            $truth['monthly_count']
        );

        $this->assertSame(
            0,
            $truth['annual_count']
        );

        $this->assertSame(
            0,
            $truth['one_off_count']
        );

        $this->assertSame(
            75.0,
            $truth[
                'recurring_monthly_equivalent'
            ]
        );

        $this->assertSame(
            900.0,
            $truth[
                'recurring_annual_equivalent'
            ]
        );
    }
}
