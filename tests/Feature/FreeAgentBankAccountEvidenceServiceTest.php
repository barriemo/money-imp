<?php

namespace Tests\Feature;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentBankAccountEvidenceService;
use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Models\ExternalConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FreeAgentBankAccountEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_accounts_are_mapped_into_evidence(): void
    {
        ExternalConnection::factory()->create([
            'name' => 'FreeAgent',
            'provider' => 'freeagent',
            'status' => 'connected',
        ]);

        $client = Mockery::mock(FreeAgentClient::class);

        $client->shouldReceive('get')
            ->once()
            ->andReturn([
                'bank_accounts' => [
                    [
                        'url' => 'https://api.freeagent.com/v2/bank_accounts/123',
                        'name' => 'Business Current Account',
                        'type' => 'StandardBankAccount',
                        'current_balance' => '177461.02',
                        'total_count' => 3669,
                        'unexplained_transaction_count' => 729,
                        'marked_for_review_count' => 1707,
                        'manually_added_transaction_count' => 922,
                        'marked_for_review_category_group_counts' => [
                            [
                                'name' => 'Trade Debtors',
                                'count' => 558,
                            ],
                        ],
                        'latest_activity_date' => '2026-07-31',
                        'bank_feed_enabled' => true,
                    ],
                ],
            ]);

        $service = new FreeAgentBankAccountEvidenceService($client);

        $evidence = $service->current();

        $this->assertCount(1, $evidence);

        $this->assertSame(
            'Business Current Account',
            $evidence->first()->name
        );

        $this->assertSame(
            177461.02,
            $evidence->first()->balance
        );

        $this->assertSame(
            729,
            $evidence->first()->unexplainedTransactionCount
        );

        $this->assertTrue(
            $evidence->first()->bankFeedEnabled
        );
    }

    public function test_missing_bank_feed_status_remains_unknown(): void
    {
        ExternalConnection::factory()->create([
            'name' => 'FreeAgent',
            'provider' => 'freeagent',
            'status' => 'connected',
        ]);

        $client = Mockery::mock(FreeAgentClient::class);

        $client->shouldReceive('get')
            ->once()
            ->andReturn([
                'bank_accounts' => [
                    [
                        'url' => 'https://api.freeagent.com/v2/bank_accounts/456',
                        'name' => 'Reserve Account',
                        'type' => 'StandardBankAccount',
                        'current_balance' => '1000.00',
                        'total_count' => 10,
                        'unexplained_transaction_count' => 0,
                        'marked_for_review_count' => 0,
                        'manually_added_transaction_count' => 0,
                        'latest_activity_date' => '2026-08-31',
                    ],
                ],
            ]);

        $service = new FreeAgentBankAccountEvidenceService($client);

        $evidence = $service->current();

        $this->assertCount(1, $evidence);

        $this->assertNull(
            $evidence->first()->bankFeedEnabled
        );
    }
}
