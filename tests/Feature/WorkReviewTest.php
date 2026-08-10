<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_work_can_be_marked_for_invoice(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $log = WorkLog::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'description' => 'Landing page updates',
            'minutes' => 60,
            'performed_at' => '2026-08-10',
            'billing_hint' => 'billable',
            'commercial_status' => 'unreviewed',
            'rate_snapshot' => 95,
            'commercial_value' => 95,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'work-review.update',
                    $log
                ),
                [
                    'commercial_status' => 'invoice',

                    'commercial_notes' => 'Add to next invoice',
                ]
            );

        $response->assertRedirect();

        $log->refresh();

        $this->assertSame(
            'invoice',
            $log->commercial_status
        );

        $this->assertSame(
            $user->id,
            $log->reviewed_by
        );

        $this->assertNotNull(
            $log->reviewed_at
        );
    }
}
