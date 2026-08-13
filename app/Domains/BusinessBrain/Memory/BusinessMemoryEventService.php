<?php

namespace App\Domains\BusinessBrain\Memory;

use App\Models\BusinessDecisionOutcome;
use App\Models\BusinessMemoryEvent;
use Illuminate\Support\Collection;

class BusinessMemoryEventService
{
    public function recordDecisionOutcome(
        BusinessDecisionOutcome $outcome
    ): BusinessMemoryEvent {
        return BusinessMemoryEvent::updateOrCreate(
            [
                'source_type' => 'business_decision_outcome',

                'source_id' => $outcome->id,
            ],
            [
                'client_id' => $outcome->client_id,

                'client' => $outcome->client,

                'type' => 'decision_outcome',

                'title' => sprintf(
                    '%s recommendation %s',
                    ucfirst(
                        $outcome->decision_type
                    ),
                    $outcome->status
                ),

                'description' => $outcome->outcome
                    ?? $outcome->reason
                    ?? $outcome->action,

                'value' => $outcome->financial_result
                    ?? $outcome->value,

                'confidence' => 100,

                'occurred_at' => $outcome->completed_at
                    ?? $outcome->decided_at
                    ?? $outcome->updated_at
                    ?? now(),

                'metadata' => [
                    'decision_type' => $outcome->decision_type,

                    'status' => $outcome->status,

                    'action' => $outcome->action,

                    'priority' => $outcome->priority,

                    'financial_result' => $outcome->financial_result,
                ],
            ]
        );
    }

    public function forClient(
        string $clientId
    ): Collection {
        return BusinessMemoryEvent::query()
            ->where(
                'client_id',
                $clientId
            )
            ->orderByDesc(
                'occurred_at'
            )
            ->get();
    }
}
