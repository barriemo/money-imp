<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Decisions\BusinessDecision;
use App\Domains\BusinessBrain\Decisions\Outcomes\BusinessDecisionOutcomeService;
use App\Models\BusinessDecisionOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDecisionOutcomeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_decision_can_be_recorded_and_completed(): void
    {
        $service =
            app(
                BusinessDecisionOutcomeService::class
            );

        $outcome =
            $service->record(
                new BusinessDecision(
                    type: 'collections',

                    clientId: 'client-1',

                    client: 'Test Client',

                    action: 'Chase overdue balance.',

                    reason: '£5,000 is overdue.',

                    priority: 90,

                    value: 5000,

                    confidence: 100
                )
            );

        $this->assertSame(
            'pending',
            $outcome->status
        );

        $outcome =
            $service->accept(
                $outcome
            );

        $this->assertSame(
            'accepted',
            $outcome->status
        );

        $outcome =
            $service->complete(
                outcome: $outcome,

                result: 'Client paid outstanding invoice.',

                financialResult: 5000
            );

        $this->assertSame(
            'completed',
            $outcome->status
        );

        $this->assertSame(
            5000.0,
            $outcome->financial_result
        );

        $this->assertNotNull(
            $outcome->completed_at
        );
    }

    public function test_same_live_decision_is_not_recorded_twice(): void
    {
        $service =
            app(
                BusinessDecisionOutcomeService::class
            );

        $decision =
            new BusinessDecision(
                type: 'collections',

                clientId: 'client-1',

                client: 'Test Client',

                action: 'Chase overdue balance.',

                reason: '£5,000 is overdue.',

                priority: 90,

                value: 5000,

                confidence: 100
            );

        $first =
            $service->record(
                $decision
            );

        $second =
            $service->record(
                $decision
            );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            1,
            BusinessDecisionOutcome::count()
        );
    }

    public function test_today_decisions_can_be_recorded_without_duplication(): void
    {
        $service =
            app(
                BusinessDecisionOutcomeService::class
            );

        $decisions =
            collect([
                new BusinessDecision(
                    type: 'collections',

                    clientId: 'client-1',

                    client: 'Test Client',

                    action: 'Chase overdue balance.',

                    reason: '£5,000 is overdue.',

                    priority: 90,

                    value: 5000,

                    confidence: 100
                ),
            ]);

        $service->recordToday(
            $decisions
        );

        $service->recordToday(
            $decisions
        );

        $this->assertSame(
            1,
            BusinessDecisionOutcome::count()
        );
    }

    public function test_completed_decision_cannot_move_back_to_accepted(): void
    {
        $service =
            app(
                BusinessDecisionOutcomeService::class
            );

        $outcome =
            $service->record(
                new BusinessDecision(
                    type: 'collections',

                    clientId: 'client-1',

                    client: 'Test Client',

                    action: 'Chase overdue balance.',

                    reason: '£5,000 is overdue.',

                    priority: 90,

                    value: 5000,

                    confidence: 100
                )
            );

        $outcome =
            $service->complete(
                outcome: $outcome,

                result: 'Client paid.',

                financialResult: 5000
            );

        $this->expectException(
            \LogicException::class
        );

        $service->accept(
            $outcome
        );
    }

    public function test_completed_decision_is_written_to_executive_memory(): void
    {
        $service =
            app(
                BusinessDecisionOutcomeService::class
            );

        $outcome =
            $service->record(
                new BusinessDecision(
                    type: 'collections',

                    clientId: 'client-memory',

                    client: 'Memory Client',

                    action: 'Chase overdue balance.',

                    reason: '£5,000 is overdue.',

                    priority: 90,

                    value: 5000,

                    confidence: 100
                )
            );

        $service->complete(
            outcome: $outcome,

            result: 'Client paid outstanding balance.',

            financialResult: 5000
        );

        $this->assertDatabaseHas(
            'business_memory_events',
            [
                'source_type' => 'business_decision_outcome',

                'source_id' => $outcome->id,

                'client' => 'Memory Client',

                'type' => 'decision_outcome',
            ]
        );
    }
}
