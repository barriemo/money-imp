<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\RevenueTruth\CommercialGapDetector;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueTruthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_truth_exposes_outstanding_and_evidence_gaps(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Revenue Truth Client',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-RT-001',

            'status' => 'overdue',

            'invoice_date' => now(),

            'due_date' => now()->subDays(7),

            'currency' => 'GBP',

            'net_amount' => 1000,

            'tax_amount' => 200,

            'gross_amount' => 1200,

            'paid_amount' => 0,

            'outstanding_amount' => 1200,
        ]);

        $truth =
            app(
                RevenueTruthService::class
            )->forClient(
                $client
            );

        $this->assertSame(
            1,
            $truth->invoiceCount
        );

        $this->assertSame(
            1200.0,
            $truth->grossInvoiced
        );

        $this->assertSame(
            1200.0,
            $truth->outstanding
        );

        $this->assertSame(
            0,
            $truth->workLogCount
        );

        $gaps =
            app(
                CommercialGapDetector::class
            )->detect(
                $truth
            );

        $this->assertTrue(
            $gaps->contains(
                fn ($gap) => $gap->type === 'outstanding_revenue'
            )
        );

        $this->assertTrue(
            $gaps->contains(
                fn ($gap) => $gap->type === 'missing_work_evidence'
            )
        );
    }
}
