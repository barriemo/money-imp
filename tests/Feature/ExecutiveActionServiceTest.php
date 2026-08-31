<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use App\Models\AccountingInvoice;
use App\Models\CapabilityDefinition;
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

    public function test_current_sync_does_not_promote_aggregate_financial_reasoning(): void
    {
        $client = Client::factory()->create([
            'name' => 'Aggregate Test Client',
            'status' => 'active',
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-AGGREGATE',
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

        $service = app(
            ExecutiveActionService::class
        );

        $service->syncCurrent(100);

        $this->assertDatabaseHas(
            'executive_actions',
            [
                'client_id' => $client->id,
                'type' => 'financial_opportunity',
                'title' => 'Recover overdue revenue',
            ]
        );

        $this->assertDatabaseMissing(
            'executive_actions',
            [
                'type' => 'receivable_recovery',
            ]
        );

        $this->assertDatabaseMissing(
            'executive_actions',
            [
                'client_id' => null,
                'type' => 'financial_opportunity',
                'title' => 'Recover overdue revenue',
            ]
        );
    }

    public function test_pending_queue_excludes_aggregate_financial_opportunity(): void
    {
        ExecutiveAction::create([
            'fingerprint' => hash('sha256', 'aggregate-financial-opportunity'),
            'type' => 'financial_opportunity',
            'title' => 'Recover overdue revenue',
            'description' => '£96,341.51 is outstanding across 76 clients.',
            'recommended_action' => 'Review and chase overdue balances.',
            'estimated_financial_impact' => 96341.51,
            'confidence' => 100,
            'urgency' => 84,
            'score' => 84,
            'status' => 'pending',
        ]);

        $client = Client::factory()->create([
            'name' => 'Queue Client',
            'status' => 'active',
        ]);

        $clientAction = ExecutiveAction::create([
            'fingerprint' => hash('sha256', 'client-financial-opportunity'),
            'client_id' => $client->id,
            'client' => $client->name,
            'type' => 'financial_opportunity',
            'title' => 'Recover overdue revenue',
            'description' => '£5,000 is overdue.',
            'recommended_action' => 'Chase client.',
            'estimated_financial_impact' => 5000,
            'confidence' => 100,
            'urgency' => 90,
            'score' => 90,
            'status' => 'pending',
        ]);

        $pending = app(ExecutiveActionService::class)->pending();

        $this->assertCount(1, $pending);
        $this->assertSame($clientAction->id, $pending->first()->id);
        $this->assertSame(
            $client->id,
            $pending->first()->client_id
        );
    }

    public function test_pending_queue_excludes_non_executive_reasoning_and_capability_actions(): void
    {
        $capability = CapabilityDefinition::create([
            'name' => 'CashManagement',
            'domain' => 'BusinessBrain',
            'area' => 'Finance',
            'owner' => 'CFOImp',
            'purpose' => 'Manage cash',
            'layers' => [
                'service',
            ],
            'status' => 'ready',
        ]);

        foreach ([
            'financial_control',
            'delivery_control',
            'receivable_recovery',
        ] as $type) {
            ExecutiveAction::create([
                'fingerprint' => hash('sha256', $type),
                'type' => $type,
                'title' => 'Non-executive reasoning',
                'description' => 'Should remain outside executive queue.',
                'recommended_action' => 'Review.',
                'confidence' => 100,
                'urgency' => 90,
                'score' => 95,
                'status' => 'pending',
            ]);
        }

        ExecutiveAction::create([
            'fingerprint' => hash('sha256', 'capability-action'),
            'type' => 'cash_management',
            'title' => 'Identify cash risks',
            'description' => 'Capability action.',
            'recommended_action' => 'Identify cash risks.',
            'confidence' => 100,
            'urgency' => 65,
            'score' => 65,
            'status' => 'pending',
            'capability_definition_id' => $capability->id,
        ]);

        $executiveClient = Client::factory()->create([
            'name' => 'Executive Queue Client',
            'status' => 'active',
        ]);

        $executive = ExecutiveAction::create([
            'fingerprint' => hash('sha256', 'executive-action'),
            'client_id' => $executiveClient->id,
            'client' => $executiveClient->name,
            'type' => 'financial_opportunity',
            'title' => 'Recover overdue revenue',
            'description' => '£12,000 is overdue.',
            'recommended_action' => 'Chase client.',
            'estimated_financial_impact' => 12000,
            'confidence' => 100,
            'urgency' => 95,
            'score' => 98,
            'status' => 'pending',
        ]);

        $pending = app(ExecutiveActionService::class)->pending();

        $this->assertCount(1, $pending);
        $this->assertSame($executive->id, $pending->first()->id);
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

    public function test_action_can_move_from_started_to_waiting_to_completed(): void
    {
        $action =
            ExecutiveAction::create([
                'fingerprint' => hash(
                    'sha256',
                    'waiting-lifecycle'
                ),

                'type' => 'financial_opportunity',

                'title' => 'Recover overdue revenue',

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
            $service->wait(
                action: $action,

                reason: 'Waiting for client payment.'
            );

        $this->assertSame(
            'waiting',
            $action->status
        );

        $this->assertSame(
            'Waiting for client payment.',
            $action->outcome
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
    }
}
