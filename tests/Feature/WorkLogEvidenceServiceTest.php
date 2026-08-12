<?php

namespace Tests\Feature;

use App\Domains\Evidence\EvidenceRepository;
use App\Domains\WorkIntelligence\Evidence\WorkLogEvidenceService;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkLogEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_log_creates_evidence(): void
    {
        $client =
            Client::factory()->create();

        $user =
            User::factory()->create();

        $workLog =
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

        app(
            WorkLogEvidenceService::class
        )->create(
            $workLog
        );

        $evidence =
            app(
                EvidenceRepository::class
            )
                ->all();

        $this->assertCount(
            1,
            $evidence
        );

        $this->assertSame(
            'work_log',
            $evidence->first()->type
        );

        $this->assertSame(
            $workLog->id,
            $evidence->first()->metadata['work_log_id']
        );
    }
}
