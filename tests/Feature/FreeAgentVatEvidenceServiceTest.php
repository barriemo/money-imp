<?php

namespace Tests\Feature;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Domains\Accounting\FreeAgent\Services\FreeAgentVatEvidenceService;
use App\Models\ExternalConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FreeAgentVatEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_vat_returns_are_mapped_into_evidence(): void
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
                'vat_returns' => [
                    [
                        'period_ends_on' => '2026-04-30',
                        'filing_status' => 'filed',
                        'payments' => [
                            [
                                'label' => 'Payment Due',
                                'amount_due' => '4523.30',
                                'due_on' => '2026-06-07',
                                'status' => 'unpaid',
                            ],
                        ],
                    ],
                ],
            ]);

        $service = new FreeAgentVatEvidenceService($client);

        $evidence = $service->current();

        $this->assertCount(1, $evidence);
        $this->assertSame(
            'Payment Due',
            $evidence->first()->label
        );
        $this->assertSame(
            4523.30,
            $evidence->first()->amountDue
        );
    }
}
