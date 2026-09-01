<?php

namespace Tests\Feature;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Domains\Accounting\FreeAgent\Services\FreeAgentExpenseEvidenceService;
use App\Models\ExternalConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FreeAgentExpenseEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_freeagent_expenses_are_mapped_into_cost_evidence(): void
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
                'expenses' => [
                    [
                        'url' => 'https://api.freeagent.com/v2/expenses/123',
                        'description' => 'Cameron Train Ticket',
                        'dated_on' => '2026-08-01',
                        'gross_value' => '-26.50',
                        'sales_tax_value' => '0',
                        'sales_tax_rate' => '0',
                        'category' => 'https://api.freeagent.com/v2/categories/365',
                        'user' => 'https://api.freeagent.com/v2/users/999',
                    ],
                ],
            ]);

        $service = new FreeAgentExpenseEvidenceService($client);

        $expenses = $service->current();

        $this->assertCount(1, $expenses);

        $this->assertSame(
            'Cameron Train Ticket',
            $expenses->first()->description
        );

        $this->assertSame(
            26.50,
            $expenses->first()->grossAmount
        );
    }
}
