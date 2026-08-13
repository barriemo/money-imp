<?php

namespace App\Domains\BusinessBrain\Actions;

use App\Domains\BusinessBrain\Memory\BusinessMemoryEventService;
use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;
use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoningService;
use App\Models\ExecutiveAction;
use Illuminate\Support\Collection;

class ExecutiveActionService
{
    public function __construct(
        private ExecutiveReasoningService $reasoning,

        private ExecutiveActionFactory $factory,

        private BusinessMemoryEventService $memory
    ) {}

    public function syncCurrent(
        int $limit = 100
    ): Collection {
        return $this->reasoning
            ->opportunities(
                $limit
            )
            ->map(
                fn (ExecutiveReasoning $reasoning) => $this->factory
                    ->createOrRefresh(
                        $reasoning
                    )
            )
            ->values();
    }

    public function pending(): Collection
    {
        return ExecutiveAction::query()
            ->where(
                'status',
                'pending'
            )
            ->orderByDesc(
                'score'
            )
            ->orderByDesc(
                'estimated_financial_impact'
            )
            ->orderBy(
                'created_at'
            )
            ->get();
    }

    public function byStatus(
        string $status
    ): Collection {
        return ExecutiveAction::query()
            ->where(
                'status',
                $status
            )
            ->orderByDesc(
                'score'
            )
            ->orderByDesc(
                'estimated_financial_impact'
            )
            ->orderBy(
                'created_at'
            )
            ->get();
    }

    public function start(
        ExecutiveAction $action
    ): ExecutiveAction {
        if ($action->status !== 'pending') {
            throw new \LogicException(
                sprintf(
                    'Cannot start executive action in %s state.',
                    $action->status
                )
            );
        }

        $action->update([
            'status' => 'started',
            'started_at' => now(),
        ]);

        return $action->refresh();
    }

    public function wait(
        ExecutiveAction $action,
        string $reason
    ): ExecutiveAction {
        if (
            ! in_array(
                $action->status,
                [
                    'pending',
                    'started',
                    'waiting',
                ],
                true
            )
        ) {
            throw new \LogicException(
                sprintf(
                    'Cannot mark executive action as waiting from %s state.',
                    $action->status
                )
            );
        }

        $action->update([
            'status' => 'waiting',
            'outcome' => $reason,
        ]);

        return $action->refresh();
    }

    public function complete(
        ExecutiveAction $action,
        string $outcome,
        ?float $financialResult = null
    ): ExecutiveAction {
        if (
            ! in_array(
                $action->status,
                [
                    'pending',
                    'started',
                    'waiting',
                ],
                true
            )
        ) {
            throw new \LogicException(
                sprintf(
                    'Cannot complete executive action in %s state.',
                    $action->status
                )
            );
        }

        $action->update([
            'status' => 'completed',
            'outcome' => $outcome,
            'financial_result' => $financialResult,
            'completed_at' => now(),
        ]);

        $action =
            $action->refresh();

        $this->memory
            ->recordExecutiveAction(
                $action
            );

        return $action;
    }
}
