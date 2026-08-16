<?php

namespace App\Domains\BusinessBrain\Project\Presenters;

use Illuminate\Support\Collection;

class ProjectBrainPresenter
{
    public function __construct(
        protected ProjectActionIntelligencePresenter $intelligencePresenter
    ) {}

    public function present(Collection $actions): array
    {
        $intelligence = $actions
            ->map(
                fn ($action) => $this->intelligencePresenter->present($action)
            );

        return [
            'urgent' => $intelligence
                ->filter(
                    fn ($item) => $item['priority']['category'] === 'urgent'
                )
                ->values()
                ->all(),

            'important' => $intelligence
                ->filter(
                    fn ($item) => $item['priority']['category'] === 'important'
                )
                ->values()
                ->all(),

            'normal' => $intelligence
                ->filter(
                    fn ($item) => $item['priority']['category'] === 'normal'
                )
                ->values()
                ->all(),
        ];
    }
}
