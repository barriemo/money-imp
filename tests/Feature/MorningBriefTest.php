<?php

namespace Tests\Feature;

use App\Domains\Dashboard\Services\MorningBriefService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MorningBriefTest extends TestCase
{
    use RefreshDatabase;

    public function test_brief_surfaces_cash_debt_and_unbilled_work(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
            'currency' => 'GBP',
            'current_balance' => 10000,
            'status' => 'active',
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1001',
            'status' => 'overdue',
            'invoice_date' => '2026-07-01',
            'due_date' => '2026-07-08',
            'gross_amount' => 1200,
            'outstanding_amount' => 1200,
        ]);

        WorkLog::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'description' => 'Website changes',
            'minutes' => 60,
            'performed_at' => '2026-08-10',
            'billing_hint' => 'billable',
            'commercial_status' => 'unreviewed',
            'rate_snapshot' => 95,
            'commercial_value' => 95,
        ]);

        $brief = app(
            MorningBriefService::class
        )->build();

        $this->assertSame(
            10000.0,
            $brief['cash']['bank']
        );

        $this->assertSame(
            1200.0,
            $brief['receivables']['outstanding']
        );

        $this->assertSame(
            1,
            $brief['work']['review_count']
        );

        $this->assertSame(
            95.0,
            $brief['work']['review_value']
        );

        $this->assertNotEmpty(
            $brief['actions']
        );
    }
}
