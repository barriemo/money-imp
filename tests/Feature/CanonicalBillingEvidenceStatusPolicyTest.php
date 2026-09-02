<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CanonicalBillingEvidenceStatusPolicy;
use App\Domains\CommercialTruth\Services\CanonicalServiceObservedBillingService;
use App\Domains\CommercialTruth\Services\CurrentCommercialPositionService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalBillingEvidenceStatusPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_admits_only_issued_invoice_statuses(): void
    {
        $policy =
            app(
                CanonicalBillingEvidenceStatusPolicy::class
            );

        $this->assertTrue(
            $policy->admits('paid')
        );

        $this->assertTrue(
            $policy->admits('overdue')
        );

        $this->assertFalse(
            $policy->admits('draft')
        );

        $this->assertFalse(
            $policy->admits('written_off')
        );

        $this->assertFalse(
            $policy->admits('refunded')
        );

        $this->assertFalse(
            $policy->admits('some_future_status')
        );

        $this->assertFalse(
            $policy->admits(null)
        );
    }

    public function test_observed_billing_uses_only_paid_and_overdue_evidence(): void
    {
        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Monthly Hosting, Security Updates & Backups',

                'status' => 'active',
            ]);

        $paid =
            $this->invoiceItem(
                client: $client,
                service: $service,
                invoiceNumber: 'STATUS-PAID',
                date: '2026-06-30',
                status: 'paid',
                amount: 50
            );

        $overdue =
            $this->invoiceItem(
                client: $client,
                service: $service,
                invoiceNumber: 'STATUS-OVERDUE',
                date: '2026-07-31',
                status: 'overdue',
                amount: 50
            );

        $this->invoiceItem(
            client: $client,
            service: $service,
            invoiceNumber: 'STATUS-DRAFT',
            date: '2026-08-01',
            status: 'draft',
            amount: 500
        );

        $this->invoiceItem(
            client: $client,
            service: $service,
            invoiceNumber: 'STATUS-WRITTEN-OFF',
            date: '2026-08-02',
            status: 'written_off',
            amount: 600
        );

        $this->invoiceItem(
            client: $client,
            service: $service,
            invoiceNumber: 'STATUS-REFUNDED',
            date: '2026-08-03',
            status: 'refunded',
            amount: 700
        );

        $observed =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertNotNull(
            $observed
        );

        $this->assertSame(
            2,
            $observed->evidenceCount
        );

        $this->assertEqualsCanonicalizing(
            [
                (string) $paid->id,
                (string) $overdue->id,
            ],
            $observed->invoiceItemIds
        );

        $this->assertSame(
            100.0,
            $observed->signedObservedNet
        );
    }

    public function test_draft_only_attributed_annual_service_does_not_support_current_value(): void
    {
        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Annual Domain Renewal – example.co.uk',

                'status' => 'active',
            ]);

        $this->invoiceItem(
            client: $client,
            service: $service,
            invoiceNumber: 'DRAFT-DOMAIN',
            date: '2026-08-27',
            status: 'draft',
            amount: 25,
            description: 'Domain Annual Renewal - example.co.uk'
        );

        $observed =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertNull(
            $observed
        );

        $position =
            app(
                CurrentCommercialPositionService::class
            )->position(
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertSame(
            0.0,
            $position
                ->canonicalCurrentObservedMonthlyEquivalent
        );

        $this->assertSame(
            0.0,
            $position
                ->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $position->canonicalActiveServiceCount
        );
    }

    private function invoiceItem(
        Client $client,
        ClientService $service,
        string $invoiceNumber,
        string $date,
        string $status,
        float $amount,
        string $description =
            'Monthly Hosting, Security Updates & Backups'
    ): AccountingInvoiceItem {
        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => $invoiceNumber,

                'invoice_date' => $date,

                'status' => $status,
            ]);

        return AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,

            'client_service_id' => $service->id,

            'description' => $description,

            'quantity' => 1,

            'unit_price' => $amount,

            'net_amount' => $amount,
        ]);
    }
}
