<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryGapDetector;
use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruthService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTruthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_truth_identifies_uninvoiced_commercial_work(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Delivery Truth Client',
            ]);

        $user =
            User::factory()->create();

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'INV-DELIVERY',

                'status' => 'paid',

                'invoice_date' => now(),

                'due_date' => now(),

                'currency' => 'GBP',

                'net_amount' => 1000,

                'tax_amount' => 200,

                'gross_amount' => 1200,

                'paid_amount' => 1200,

                'outstanding_amount' => 0,
            ]);

        WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => 'Billable development work',

            'minutes' => 120,

            'performed_at' => today(),

            'billing_hint' => 'billable',

            'commercial_status' => 'reviewed',

            'rate_snapshot' => 100,

            'commercial_value' => 200,

            'accounting_invoice_id' => $invoice->id,
        ]);

        WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => 'Additional billable development work',

            'minutes' => 180,

            'performed_at' => today(),

            'billing_hint' => 'billable',

            'commercial_status' => 'reviewed',

            'rate_snapshot' => 100,

            'commercial_value' => 300,

            'accounting_invoice_id' => null,
        ]);

        $truth =
            app(
                DeliveryTruthService::class
            )->forClient(
                $client
            );

        $this->assertSame(
            2,
            $truth->workLogCount
        );

        $this->assertSame(
            1,
            $truth->invoicedWorkLogCount
        );

        $this->assertSame(
            1,
            $truth->uninvoicedWorkLogCount
        );

        $this->assertSame(
            500.0,
            $truth->commercialValue
        );

        $this->assertSame(
            200.0,
            $truth->invoicedCommercialValue
        );

        $this->assertSame(
            300.0,
            $truth->uninvoicedCommercialValue
        );

        $this->assertSame(
            50,
            $truth->invoiceLinkageConfidence
        );

        $gaps =
            app(
                DeliveryGapDetector::class
            )->detect(
                $truth
            );

        $this->assertTrue(
            $gaps->contains(
                fn ($gap) => $gap->type === 'uninvoiced_delivery'
            )
        );

        $this->assertTrue(
            $gaps->contains(
                fn ($gap) => $gap->type === 'incomplete_invoice_linkage'
            )
        );
    }
}
