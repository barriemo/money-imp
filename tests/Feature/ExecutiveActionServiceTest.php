<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\ExecutiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveActionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_reasoning_can_be_persisted_as_executive_action_without_duplication(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Action Client',
                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-ACTION',
            'status' => 'overdue',
            'invoice_date' => now()->subDays(30),
            'due_date' => now()->subDays(20),
            'currency' => 'GBP',
            'net_amount' => 10000,
            'tax_amount' => 2000,
            'gross_amount' => 12000,
            'paid_amount' => 0,
            'outstanding_amount' => 12000,
        ]);

        $service =
            app(
                ExecutiveActionService::class
            );

        $service->syncCurrent();
        $service->syncCurrent();

        $this->assertSame(
            1,
            ExecutiveAction::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'title',
                    'Recover overdue revenue'
                )
                ->count()
        );

        $action =
            ExecutiveAction::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'title',
                    'Recover overdue revenue'
                )
                ->firstOrFail();

        $this->assertSame(
            'pending',
            $action->status
        );

        $this->assertSame(
            '12000.00',
            $action->estimated_financial_impact
        );

        $this->assertGreaterThanOrEqual(
            90,
            $action->score
        );
    }

    public function test_action_can_move_from_pending_to_started_to_completed(): void
    {
        $action =
            ExecutiveAction::create([
                'fingerprint' => hash('sha256', 'lifecycle'),
                'type' => 'financial_opportunity',
                'title' => 'Recover revenue',
                'description' => 'Revenue is overdue.',
                'recommended_action' => 'Chase client.',
                'confidence' => 100,
                'urgency' => 95,
                'score' => 98,
                'status' => 'pending',
            ]);

        $service =
            app(
                ExecutiveActionService::class
            );

        $action =
            $service->start(
                $action
            );

        $this->assertSame(
            'started',
            $action->status
        );

        $action =
            $service->complete(
                action: $action,
                outcome: 'Client paid.',
                financialResult: 5000
            );

        $this->assertSame(
            'completed',
            $action->status
        );

        $this->assertSame(
            '5000.00',
            $action->financial_result
        );

        $this->assertNotNull(
            $action->completed_at
        );
    }

    public function test_completed_action_cannot_be_started_again(): void
    {
        $action =
            ExecutiveAction::create([
                'fingerprint' => hash(
                    'sha256',
                    'completed-action'
                ),

                'type' => 'financial_opportunity',

                'title' => 'Recover revenue',

                'description' => 'Revenue is overdue.',

                'recommended_action' => 'Chase client.',

                'confidence' => 100,

                'urgency' => 95,

                'score' => 98,

                'status' => 'pending',
            ]);

        $service =
            app(
                ExecutiveActionService::class
            );

        $action =
            $service->complete(
                action: $action,

                outcome: 'Client paid.',

                financialResult: 5000
            );

        $this->expectException(
            \LogicException::class
        );

        $service->start(
            $action
        );
    }

    public function test_completed_action_is_written_to_executive_memory(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Memory Action Client',
            ]);

        $action =
            ExecutiveAction::create([
                'fingerprint' => hash(
                    'sha256',
                    'memory-action'
                ),

                'client_id' => $client->id,

                'client' => $client->name,

                'type' => 'financial_opportunity',

                'title' => 'Recover overdue revenue',

                'description' => '£5,000 is overdue.',

                'recommended_action' => 'Chase client.',

                'estimated_financial_impact' => 5000,

                'estimated_effort_minutes' => 10,

                'confidence' => 100,

                'urgency' => 95,

                'score' => 98,

                'status' => 'pending',
            ]);

        $service =
            app(
                ExecutiveActionService::class
            );

        $service->complete(
            action: $action,

            outcome: 'Client paid after follow-up.',

            financialResult: 5000
        );

        $this->assertDatabaseHas(
            'business_memory_events',
            [
                'source_type' => 'executive_action',

                'source_id' => $action->id,

                'client_id' => $client->id,

                'type' => 'executive_action_outcome',

                'description' => 'Client paid after follow-up.',
            ]
        );
    }
}
