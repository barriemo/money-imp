<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummariser;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoveryOpportunitySummariserTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarises_unrecovered_client_value(): void
    {
        $client =
            Client::factory()->create();

        $user =
            User::factory()->create();

        foreach ([380, 760, 950] as $value) {
            WorkLog::create([
                'client_id' => $client->id,

                'user_id' => $user->id,

                'description' => 'Completed client work',

                'minutes' => 60,

                'performed_at' => now(),

                'billing_hint' => 'billable',

                'commercial_status' => 'unreviewed',

                'rate_snapshot' => 95,

                'commercial_value' => $value,
            ]);
        }

        $summary =
            app(
                RecoveryOpportunitySummariser::class
            )->summarise(
                $client
            );

        $this->assertSame(
            3,
            $summary->opportunityCount
        );

        $this->assertSame(
            2090.0,
            $summary->totalValue
        );

        $this->assertSame(
            950.0,
            $summary->highestValue
        );

        $this->assertSame(
            90,
            $summary->confidence
        );
    }
}
