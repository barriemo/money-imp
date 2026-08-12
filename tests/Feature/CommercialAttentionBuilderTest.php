<?php

namespace Tests\Feature;

use App\Domains\Dashboard\MorningBrief\CommercialAttentionBuilder;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAttentionBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_morning_commercial_attention_highlights_recovery_risks(): void
    {
        $client = Client::factory()->create([
            'status' => 'active',
        ]);

        $user = User::factory()->create();

        WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => 'CRM integration work',

            'minutes' => 120,

            'performed_at' => now(),

            'billing_hint' => 'billable',

            'commercial_status' => 'unreviewed',

            'rate_snapshot' => 95,

            'commercial_value' => 190,
        ]);

        $attention =
            app(
                CommercialAttentionBuilder::class
            )
                ->build();

        $this->assertSame(
            1,
            $attention->clientCount
        );

        $this->assertSame(
            190.0,
            $attention->recoverableValue
        );

        $this->assertSame(
            1,
            $attention->openWorkLogs
        );

        $this->assertSame(
            $client->name,
            $attention->highPriorityClients[0]['client']
        );
    }
}
