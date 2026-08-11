<?php

namespace Tests\Feature;

use App\Domains\CheerfulCharlie\Daily\CharlieDailyService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieDailyBriefTest extends TestCase
{
    use RefreshDatabase;

    public function test_charlie_builds_daily_brief_across_active_clients(): void
    {
        Client::factory()->create([
            'status' => 'active',
        ]);

        Client::factory()->create([
            'status' => 'active',
        ]);

        $brief = app(
            CharlieDailyService::class
        )->build();

        $this->assertSame(
            2,
            $brief->client_count
        );

        $this->assertGreaterThan(
            0,
            $brief->new_finding_count
        );

        $this->assertIsArray(
            $brief->summary
        );

        $this->assertArrayHasKey(
            'top_findings',
            $brief->summary
        );
    }
}
