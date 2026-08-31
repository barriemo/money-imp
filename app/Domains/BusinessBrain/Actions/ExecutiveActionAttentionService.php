<?php

namespace App\Domains\BusinessBrain\Actions;

use App\Models\ExecutiveAction;
use Illuminate\Support\Collection;

final class ExecutiveActionAttentionService
{
    public function __construct(
        private ExecutiveActionService $actions
    ) {}

    public function current(int $limit = 5): Collection
    {
        return $this->actions
            ->pending()
            ->sortByDesc(
                fn (ExecutiveAction $action) => [
                    $action->score,
                    $action->estimated_financial_impact ?? 0,
                    $action->urgency,
                ]
            )
            ->take($limit)
            ->values();
    }
}
