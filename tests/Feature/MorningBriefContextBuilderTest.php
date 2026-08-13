<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\MorningBrief\Context\MorningBriefContextBuilder;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MorningBriefContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_morning_brief_context_is_created_from_client(): void
    {
        $client = Client::factory()->create([
            'name' => 'Walker',
        ]);

        $user = User::factory()->create();

        WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => 'Unbilled strategic work',

            'minutes' => 120,

            'commercial_value' => 1000,

            'performed_at' => now(),

            'accounting_invoice_id' => null,
        ]);

        $context =
            app(
                MorningBriefContextBuilder::class
            )->build(
                $client
            );

        $this->assertSame(
            'Walker',
            $context->client
        );

        $this->assertSame(
            1000.0,
            $context->recovery->totalValue
        );

        $this->assertNotNull(
            $context->allocation
        );
    }
}
