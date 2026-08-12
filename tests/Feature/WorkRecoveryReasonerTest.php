<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Recovery\WorkRecoveryReasoner;
use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkRecoveryReasonerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrecovered_work_requires_recovery(): void
    {
        $client = Client::factory()->create();

        $user = User::factory()->create();

        $workLog = WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => 'Fixed CRM integration',

            'minutes' => 120,

            'performed_at' => now(),

            'billing_hint' => 'billable',

            'commercial_status' => 'unreviewed',

            'rate_snapshot' => 95,

            'commercial_value' => 190,
        ]);

        $assessment =
            app(
                WorkRecoveryReasoner::class
            )->assess(
                $workLog
            );

        $this->assertSame(
            'recovery_required',
            $assessment->state
        );

        $this->assertSame(
            190.0,
            $assessment->value
        );
    }

    public function test_invoiced_work_is_recovered(): void
    {
        $client = Client::factory()->create();

        $user = User::factory()->create();

        $workLog = WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => 'Completed project work',

            'minutes' => 60,

            'performed_at' => now(),

            'billing_hint' => 'billable',

            'commercial_status' => 'invoice',

            'rate_snapshot' => 95,

            'commercial_value' => 95,

            'accounting_invoice_id' => AccountingInvoice::factory()->create([
                'client_id' => $client->id,
            ])->id,
        ]);

        $assessment =
            app(
                WorkRecoveryReasoner::class
            )->assess(
                $workLog
            );

        $this->assertSame(
            'recovered',
            $assessment->state
        );
    }
}
