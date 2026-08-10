<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_client_work(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'name' => 'Economy',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(
                route('work-log.store'),
                [
                    'client_id' => $client->id,
                    'user_id' => $user->id,
                    'description' => 'Updated landing page copy',

                    'minutes' => 45,

                    'performed_at' => '2026-08-10',

                    'billing_hint' => 'billable',
                ]
            );

        $response->assertRedirect();

        $log = WorkLog::firstOrFail();

        $this->assertSame(
            $client->id,
            $log->client_id
        );

        $this->assertSame(
            45,
            $log->minutes
        );

        $this->assertSame(
            'billable',
            $log->billing_hint
        );

        $this->assertSame(
            '71.25',
            $log->commercial_value
        );

        $this->assertSame(
            'unreviewed',
            $log->commercial_status
        );
    }
}
