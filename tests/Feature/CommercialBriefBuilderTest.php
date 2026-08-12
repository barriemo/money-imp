<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\CommercialBrief\CommercialBriefBuilder;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialBriefBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_commercial_brief_identifies_recovery_risk(): void
    {
        $client = Client::factory()->create();

        $user = User::factory()->create();

        WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => 'Built CRM integration',

            'minutes' => 120,

            'performed_at' => now(),

            'billing_hint' => 'billable',

            'commercial_status' => 'unreviewed',

            'rate_snapshot' => 95,

            'commercial_value' => 190,
        ]);

        $brief =
            app(
                CommercialBriefBuilder::class
            )
                ->build(
                    $client
                );

        $this->assertSame(
            'attention_required',
            $brief->health
        );

        $this->assertSame(
            190.0,
            $brief->recoveryValue
        );

        $this->assertSame(
            1,
            $brief->recoveryCount
        );
    }
}
