<?php

namespace Tests\Feature;

use App\Domains\Billing\Services\MonthlyBillingAuditService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyBillingAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_client_with_no_july_invoice_is_flagged_missing(): void
    {
        $client = Client::create([
            'name' => 'Walker',
            'status' => 'active',
        ]);

        foreach ([
            '2026-03-01',
            '2026-04-01',
            '2026-05-01',
            '2026-06-01',
        ] as $date) {
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-'.$date,
                'status' => 'paid',
                'invoice_date' => $date,
                'gross_amount' => 1620,
                'outstanding_amount' => 0,
            ]);
        }

        $result = app(MonthlyBillingAuditService::class)
            ->audit(CarbonImmutable::create(2026, 7, 1));

        $this->assertSame(1, $result['summary']['expected_clients']);
        $this->assertSame(1, $result['summary']['missing']);
        $this->assertSame(0, $result['summary']['issued']);

        $row = $result['rows']->first();

        $this->assertSame('Walker', $row['client']->name);
        $this->assertSame('missing', $row['status']);
        $this->assertSame(1620.0, $row['expected_amount']);
        $this->assertSame(0.0, $row['actual_amount']);
        $this->assertSame(1620.0, $row['potential_missing_amount']);
    }

    public function test_july_invoice_satisfies_expected_monthly_billing(): void
    {
        $client = Client::create([
            'name' => 'MML Law',
            'status' => 'active',
        ]);

        foreach ([
            '2026-03-01',
            '2026-04-01',
            '2026-05-01',
            '2026-06-01',
            '2026-07-01',
        ] as $date) {
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'INV-'.$date,
                'status' => 'paid',
                'invoice_date' => $date,
                'gross_amount' => 1800,
                'outstanding_amount' => 0,
            ]);
        }

        $result = app(MonthlyBillingAuditService::class)
            ->audit(CarbonImmutable::create(2026, 7, 1));

        $this->assertSame(1, $result['summary']['issued']);
        $this->assertSame(0, $result['summary']['missing']);
        $this->assertSame('issued', $result['rows']->first()['status']);
    }
}
