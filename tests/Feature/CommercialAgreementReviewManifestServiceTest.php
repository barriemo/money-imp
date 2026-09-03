<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CommercialAgreementReviewManifestService;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommercialAgreementReviewManifestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_contains_only_stable_current_monthly_candidates_and_is_read_only(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Manifest Client',
            ]);

        $routine =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Routine Monthly Service',

                'type' => 'service',

                'status' => 'active',
            ]);

        $annual =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Annual Service',

                'type' => 'service',

                'status' => 'active',
            ]);

        foreach (
            [
                '2026-05-31',
                '2026-06-30',
                '2026-07-31',
                '2026-08-31',
            ] as $index => $date
        ) {
            $this->invoiceLine(
                clientId: $client->id,

                serviceId: $routine->id,

                invoiceNumber: 'M'.($index + 1),

                date: $date,

                unitPrice: 75
            );
        }

        foreach (
            [
                '2025-06-01',
                '2026-06-01',
            ] as $index => $date
        ) {
            $this->invoiceLine(
                clientId: $client->id,

                serviceId: $annual->id,

                invoiceNumber: 'A'.($index + 1),

                date: $date,

                unitPrice: 120
            );
        }

        $before = [
            'agreements' => CommercialAgreement::count(),

            'coverage' => CommercialAgreementCoverageReview::count(),
        ];

        $items =
            app(
                CommercialAgreementReviewManifestService::class
            )->routine(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $after = [
            'agreements' => CommercialAgreement::count(),

            'coverage' => CommercialAgreementCoverageReview::count(),
        ];

        $this->assertCount(
            1,
            $items
        );

        $this->assertSame(
            $routine->id,
            $items->first()[
                'client_service_id'
            ]
        );

        $this->assertSame(
            'monthly',
            $items->first()[
                'proposed_cadence'
            ]
        );

        $this->assertSame(
            7500,
            $items->first()[
                'proposed_amount_pence'
            ]
        );

        $this->assertSame(
            $before,
            $after
        );
    }

    private function invoiceLine(
        string $clientId,
        string $serviceId,
        string $invoiceNumber,
        string $date,
        float $unitPrice
    ): void {
        $invoiceId =
            (string) Str::uuid();

        DB::table(
            'accounting_invoices'
        )->insert([
            'id' => $invoiceId,

            'client_id' => $clientId,

            'invoice_number' => $invoiceNumber,

            'status' => 'paid',

            'invoice_date' => $date,

            'currency' => 'GBP',

            'net_amount' => $unitPrice,

            'tax_amount' => 0,

            'gross_amount' => $unitPrice,

            'paid_amount' => $unitPrice,

            'outstanding_amount' => 0,

            'created_at' => now(),

            'updated_at' => now(),
        ]);

        DB::table(
            'accounting_invoice_items'
        )->insert([
            'id' => (string) Str::uuid(),

            'accounting_invoice_id' => $invoiceId,

            'client_service_id' => $serviceId,

            'description' => 'Test service',

            'quantity' => 1,

            'unit_price' => $unitPrice,

            'net_amount' => $unitPrice,

            'tax_amount' => 0,

            'gross_amount' => $unitPrice,

            'created_at' => now(),

            'updated_at' => now(),
        ]);
    }
}
