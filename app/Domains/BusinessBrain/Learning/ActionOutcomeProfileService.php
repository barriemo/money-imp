<?php

namespace App\Domains\BusinessBrain\Learning;

use App\Models\ExecutiveAction;
use Illuminate\Support\Collection;

class ActionOutcomeProfileService
{
    public function byType(): Collection
    {
        return ExecutiveAction::query()
            ->where(
                'status',
                'completed'
            )
            ->get()
            ->groupBy(
                'type'
            )
            ->map(
                fn (Collection $actions, string $type) => $this->typeProfile(
                    $type,
                    $actions
                )
            )
            ->sortByDesc(
                'totalFinancialResult'
            )
            ->values();
    }

    public function forType(
        string $type
    ): ?ActionOutcomeProfile {
        $actions =
            ExecutiveAction::query()
                ->where(
                    'status',
                    'completed'
                )
                ->where(
                    'type',
                    $type
                )
                ->get();

        if ($actions->isEmpty()) {
            return null;
        }

        return $this->typeProfile(
            $type,
            $actions
        );
    }

    public function forClient(
        string $clientId
    ): ?ClientActionOutcomeProfile {
        $actions =
            ExecutiveAction::query()
                ->where(
                    'status',
                    'completed'
                )
                ->where(
                    'client_id',
                    $clientId
                )
                ->get();

        if ($actions->isEmpty()) {
            return null;
        }

        $successful =
            $actions
                ->filter(
                    fn (ExecutiveAction $action) => $this->financialSuccess(
                        $action
                    )
                );

        $total =
            (float) $actions
                ->sum(
                    fn (ExecutiveAction $action) => (float) (
                        $action->financial_result
                        ?? 0
                    )
                );

        return new ClientActionOutcomeProfile(
            clientId: $clientId,

            client: (string) $actions
                ->first()
                ->client,

            completedCount: $actions->count(),

            financialSuccessCount: $successful->count(),

            totalFinancialResult: $total,

            averageFinancialResult: $actions->count() > 0
                ? $total / $actions->count()
                : 0,

            financialSuccessRate: $actions->count() > 0
                ? (int) round(
                    ($successful->count() / $actions->count())
                    * 100
                )
                : 0,

            averageCompletionHours: $this->averageCompletionHours(
                $actions
            )
        );
    }

    private function typeProfile(
        string $type,
        Collection $actions
    ): ActionOutcomeProfile {
        $successful =
            $actions
                ->filter(
                    fn (ExecutiveAction $action) => $this->financialSuccess(
                        $action
                    )
                );

        $total =
            (float) $actions
                ->sum(
                    fn (ExecutiveAction $action) => (float) (
                        $action->financial_result
                        ?? 0
                    )
                );

        return new ActionOutcomeProfile(
            type: $type,

            completedCount: $actions->count(),

            financialSuccessCount: $successful->count(),

            totalFinancialResult: $total,

            averageFinancialResult: $actions->count() > 0
                ? $total / $actions->count()
                : 0,

            financialSuccessRate: $actions->count() > 0
                ? (int) round(
                    ($successful->count() / $actions->count())
                    * 100
                )
                : 0,

            averageCompletionHours: $this->averageCompletionHours(
                $actions
            )
        );
    }

    private function financialSuccess(
        ExecutiveAction $action
    ): bool {
        return $action->financial_result !== null
            && (float) $action->financial_result > 0;
    }

    private function averageCompletionHours(
        Collection $actions
    ): ?float {
        $durations =
            $actions
                ->filter(
                    fn (ExecutiveAction $action) => $action->started_at !== null
                        && $action->completed_at !== null
                )
                ->map(
                    fn (ExecutiveAction $action) => $action
                        ->started_at
                        ->diffInMinutes(
                            $action->completed_at
                        ) / 60
                );

        if ($durations->isEmpty()) {
            return null;
        }

        return round(
            (float) $durations->avg(),
            2
        );
    }
}
