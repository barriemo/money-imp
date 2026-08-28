<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\RevenueTruth\ReceivableRealityService;
use App\Models\AccountingInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableRealityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receivables_separate_recoverable_written_off_and_draft_amounts(): void
    {
        AccountingInvoice::create([
            'invoice_number' => 'REC-001',
            'status' => 'overdue',
            'invoice_date' => now()->subDays(60),
            'due_date' => now()->subDays(30),
            'currency' => 'GBP',
            'net_amount' => 1000,
            'tax_amount' => 200,
            'gross_amount' => 1200,
            'paid_amount' => 0,
            'outstanding_amount' => 1200,
        ]);

        AccountingInvoice::create([
            'invoice_number' => 'WO-001',
            'status' => 'written_off',
            'invoice_date' => now()->subDays(90),
            'due_date' => now()->subDays(60),
            'currency' => 'GBP',
            'net_amount' => 2000,
            'tax_amount' => 400,
            'gross_amount' => 2400,
            'paid_amount' => 0,
            'outstanding_amount' => 2400,
        ]);

        AccountingInvoice::create([
            'invoice_number' => 'DRAFT-001',
            'status' => 'draft',
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
            'currency' => 'GBP',
            'net_amount' => 500,
            'tax_amount' => 100,
            'gross_amount' => 600,
            'paid_amount' => 0,
            'outstanding_amount' => 600,
        ]);

        $reality = app(ReceivableRealityService::class)->current();

        $this->assertSame(1200.0, $reality->reportedOutstanding);
        $this->assertSame(1, $reality->invoiceCount);
        $this->assertSame(1, $reality->overdueInvoiceCount);
        $this->assertSame(4200.0, $reality->ledgerOutstanding);
        $this->assertSame(2400.0, $reality->writtenOffAmount);
        $this->assertSame(600.0, $reality->draftAmount);

        $this->assertCount(1, $reality->priorityInvoices);
        $this->assertSame(
            'REC-001',
            $reality->priorityInvoices[0]['invoice_number']
        );
    }
}
