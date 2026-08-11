<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Attribution\HostingAttributionEngine;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\AttributionCandidate;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostingAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosting_invoice_without_known_server_creates_candidate(): void
    {
        $client =
            Client::factory()->create();

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'HOST-1',

                'invoice_date' => now()->toDateString(),

                'status' => 'sent',
            ]);

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,

            'description' => 'Monthly Hosting, Security Updates & Backups',

            'quantity' => 1,

            'unit_price' => 75,

            'net_amount' => 75,
        ]);

        $candidates = app(
            HostingAttributionEngine::class
        )->candidates();

        $this->assertCount(
            1,
            $candidates
        );

        $candidate =
            $candidates->first();

        $this->assertSame(
            $client->id,
            $candidate->client_id
        );

        $this->assertNull(
            $candidate->supplier_asset_id
        );

        $this->assertSame(
            95,
            $candidate->confidence
        );

        $this->assertSame(
            'candidate',
            $candidate->status
        );

        $this->assertTrue(
            $candidate->evidence[
                'is_hosting'
            ]
        );
    }

    public function test_recurring_hosting_history_collapses_into_one_generic_candidate(): void
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

                    'invoice_number' => 'HOST-'
                        .$date,

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

        app(
            HostingAttributionEngine::class
        )->candidates();

        $candidates =
            AttributionCandidate::query()
                ->where(
                    'subject_type',
                    'client'
                )
                ->where(
                    'subject_id',
                    $client->id
                )
                ->where(
                    'relationship_type',
                    'hosted_on'
                )
                ->get();

        $this->assertCount(
            1,
            $candidates
        );

        $this->assertCount(
            3,
            $candidates
                ->first()
                ->evidence
        );

        $this->assertGreaterThan(
            95,
            $candidates
                ->first()
                ->confidence
        );

        $this->assertLessThanOrEqual(
            99,
            $candidates
                ->first()
                ->confidence
        );
    }
}
