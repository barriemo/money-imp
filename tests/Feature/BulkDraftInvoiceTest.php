<?php

namespace Tests\Feature;

use App\Domains\Billing\Services\BulkDraftInvoiceService;
use App\Domains\Billing\Services\FreeAgentDraftInvoiceService;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class BulkDraftInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_service_creates_each_selected_draft_independently(): void
    {
        $first = Client::create([
            'name' => 'Affertons Limited',
            'status' => 'active',
        ]);

        $second = Client::create([
            'name' => 'AGM Stone',
            'status' => 'active',
        ]);

        $this->mock(
            FreeAgentDraftInvoiceService::class,
            function (MockInterface $mock) use ($first, $second): void {
                $mock->shouldReceive('createMonthlyDraft')
                    ->once()
                    ->withArgs(
                        fn ($client, $month) => $client->is($first)
                            && $month->isSameDay(
                                CarbonImmutable::create(2026, 7, 1)
                            )
                    )
                    ->andReturn([
                        'reference' => '3001',
                    ]);

                $mock->shouldReceive('createMonthlyDraft')
                    ->once()
                    ->withArgs(
                        fn ($client, $month) => $client->is($second)
                            && $month->isSameDay(
                                CarbonImmutable::create(2026, 7, 1)
                            )
                    )
                    ->andReturn([
                        'reference' => '3002',
                    ]);
            }
        );

        $result = app(
            BulkDraftInvoiceService::class
        )->create(
            [$first->id, $second->id],
            CarbonImmutable::create(2026, 7, 1)
        );

        $this->assertCount(2, $result['created']);
        $this->assertCount(0, $result['failed']);
    }

    public function test_one_failure_does_not_stop_remaining_drafts(): void
    {
        $first = Client::create([
            'name' => 'Affertons Limited',
            'status' => 'active',
        ]);

        $second = Client::create([
            'name' => 'AGM Stone',
            'status' => 'active',
        ]);

        $this->mock(
            FreeAgentDraftInvoiceService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('createMonthlyDraft')
                    ->once()
                    ->andThrow(
                        new \RuntimeException('FreeAgent rejected draft.')
                    );

                $mock->shouldReceive('createMonthlyDraft')
                    ->once()
                    ->andReturn([
                        'reference' => '3002',
                    ]);
            }
        );

        $result = app(
            BulkDraftInvoiceService::class
        )->create(
            [$first->id, $second->id],
            CarbonImmutable::create(2026, 7, 1)
        );

        $this->assertCount(1, $result['created']);
        $this->assertCount(1, $result['failed']);
    }
}
