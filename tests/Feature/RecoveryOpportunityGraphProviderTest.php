<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Recovery\Graph\WorkRecoveryGraphProvider;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkRecoveryGraphProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrecovered_work_contributes_recovery_state(): void
    {
        $client = Client::factory()->create();

        $user = User::factory()->create();

        WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => 'Fixed Walker CRM integration',

            'minutes' => 120,

            'performed_at' => now(),

            'billing_hint' => 'billable',

            'commercial_status' => 'unreviewed',

            'rate_snapshot' => 95,

            'commercial_value' => 190,
        ]);

        $graph =
            app(
                WorkRecoveryGraphProvider::class
            )
                ->build(
                    $client->id
                );

        $this->assertCount(
            1,
            $graph->nodes
        );

        $this->assertSame(
            'recovery_required',
            $graph->nodes->first()->label
        );

        $this->assertCount(
            1,
            $graph->edges
        );
    }
}
