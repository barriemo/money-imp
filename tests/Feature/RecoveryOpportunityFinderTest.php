<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Recovery\RecoveryOpportunityFinder;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoveryOpportunityFinderTest extends TestCase
{
    use RefreshDatabase;

    public function test_identifies_unrecovered_client_value(): void
    {
        $client =
            Client::factory()->create();

        $user =
            User::factory()->create();

        $workLog =
            WorkLog::create([
                'client_id' => $client->id,

                'user_id' => $user->id,

                'description' => 'Completed CRM integration',

                'minutes' => 240,

                'performed_at' => now(),

                'billing_hint' => 'billable',

                'commercial_status' => 'unreviewed',

                'rate_snapshot' => 95,

                'commercial_value' => 380,
            ]);

        $opportunities =
            app(
                RecoveryOpportunityFinder::class
            )->find(
                $client
            );

        $this->assertCount(
            1,
            $opportunities
        );

        $this->assertSame(
            $workLog->id,
            $opportunities->first()->workLogId
        );

        $this->assertSame(
            380.0,
            $opportunities->first()->value
        );
    }
}
