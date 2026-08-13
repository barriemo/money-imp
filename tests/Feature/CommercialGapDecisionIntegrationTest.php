<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Decisions\BusinessDecisionService;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialGapDecisionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_uninvoiced_delivery_becomes_executive_decision(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Commercial Gap Client',

                'status' => 'active',
            ]);

        $user =
            User::factory()->create();

        WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => 'Completed billable delivery',

            'minutes' => 180,

            'performed_at' => today(),

            'billing_hint' => 'billable',

            'commercial_status' => 'reviewed',

            'rate_snapshot' => 100,

            'commercial_value' => 300,

            'accounting_invoice_id' => null,
        ]);

        $decisions =
            app(
                BusinessDecisionService::class
            )->today();

        $decision =
            $decisions
                ->first(
                    fn ($decision) => $decision->clientId === $client->id
                        && $decision->type === 'invoice_delivery'
                );

        $this->assertNotNull(
            $decision
        );

        $this->assertSame(
            300.0,
            $decision->value
        );

        $this->assertSame(
            'Review completed commercial work and create the required invoice.',
            $decision->action
        );

        $this->assertSame(
            95,
            $decision->priority
        );
    }
}
