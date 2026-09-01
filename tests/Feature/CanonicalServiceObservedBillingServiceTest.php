<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CanonicalServiceObservedBillingService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalServiceObservedBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attributed_monthly_history_establishes_current_observed_billing(): void
    {
        [
            $client,
            $service,
        ] = $this->service();

        foreach (
            [
                '2026-06-30',
                '2026-07-31',
                '2026-08-31',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,
                service: $service,
                date: $date,
                amount: 75
            );
        }

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            );

        $this->assertNotNull(
            $truth
        );

        $this->assertSame(
            'monthly',
            $truth->cadence
        );

        $this->assertTrue(
            $truth->recurringEvidence
        );

        $this->assertSame(
            'current',
            $truth->freshness
        );

        $this->assertSame(
            75.0,
            $truth
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            3,
            $truth->evidenceCount
        );
    }

    public function test_unattributed_new_price_does_not_change_canonical_observed_value(): void
    {
        [
            $client,
            $service,
        ] = $this->service();

        foreach (
            [
                '2026-06-30',
                '2026-07-31',
                '2026-08-31',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,
                service: $service,
                date: $date,
                amount: 75
            );
        }

        /*
         * New evidence exists, but a human has not yet approved
         * its attribution to this canonical service.
         */
        $this->invoiceItem(
            client: $client,
            service: null,
            date: '2026-09-30',
            amount: 100
        );

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-10-01'
                )
            );

        $this->assertNotNull(
            $truth
        );

        $this->assertSame(
            75.0,
            $truth
                ->latestObservedUnitPrice
        );

        $this->assertSame(
            75.0,
            $truth
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            3,
            $truth->evidenceCount
        );
    }

    public function test_approved_new_attribution_can_update_observed_value(): void
    {
        [
            $client,
            $service,
        ] = $this->service();

        foreach (
            [
                '2026-06-30',
                '2026-07-31',
                '2026-08-31',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,
                service: $service,
                date: $date,
                amount: 75
            );
        }

        $this->invoiceItem(
            client: $client,
            service: $service,
            date: '2026-09-30',
            amount: 100
        );

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-10-01'
                )
            );

        $this->assertNotNull(
            $truth
        );

        $this->assertSame(
            100.0,
            $truth
                ->latestObservedUnitPrice
        );

        $this->assertSame(
            100.0,
            $truth
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            4,
            $truth->evidenceCount
        );
    }

    public function test_stale_attributed_monthly_history_is_not_current_value(): void
    {
        [
            $client,
            $service,
        ] = $this->service();

        foreach (
            [
                '2026-01-31',
                '2026-02-28',
                '2026-03-31',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,
                service: $service,
                date: $date,
                amount: 75
            );
        }

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            );

        $this->assertNotNull(
            $truth
        );

        $this->assertSame(
            'stale',
            $truth->freshness
        );

        $this->assertNull(
            $truth
                ->currentMonthlyEquivalent
        );
    }

    public function test_single_attributed_observation_is_not_promoted_to_recurring_value(): void
    {
        [
            $client,
            $service,
        ] = $this->service();

        $this->invoiceItem(
            client: $client,
            service: $service,
            date: '2026-08-31',
            amount: 75
        );

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            );

        $this->assertNotNull(
            $truth
        );

        $this->assertSame(
            'one_off',
            $truth->cadence
        );

        $this->assertFalse(
            $truth->recurringEvidence
        );

        $this->assertNull(
            $truth
                ->currentMonthlyEquivalent
        );
    }

    private function service(): array
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

        return [
            $client,
            $service,
        ];
    }

    private function invoiceItem(
        Client $client,
        ?ClientService $service,
        string $date,
        float $amount
    ): AccountingInvoiceItem {
        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => (string) str()->uuid(),
                'invoice_date' => $date,
                'status' => 'paid',
            ]);

        return AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,
            'client_service_id' => $service?->id,
            'description' => 'Monthly Hosting, Security Updates & Backups',
            'quantity' => 1,
            'unit_price' => $amount,
            'net_amount' => $amount,
        ]);
    }
}
