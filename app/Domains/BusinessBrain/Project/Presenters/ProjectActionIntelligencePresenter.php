<?php

namespace App\Domains\BusinessBrain\Project\Presenters;

use App\Domains\BusinessBrain\Project\Services\ProjectActionOutcomeEvaluator;
use App\Domains\BusinessBrain\Project\Services\ProjectActionPrioritiser;
use App\Models\ProjectAction;

class ProjectActionIntelligencePresenter
{
    public function __construct(
        protected ProjectActionPresenter $actionPresenter,
        protected ProjectActionTimelinePresenter $timelinePresenter,
        protected ProjectActionOutcomeEvaluator $evaluator,
        protected ProjectActionPrioritiser $prioritiser,
    ) {}

    public function present(ProjectAction $action): array
    {
        $action->loadMissing([
            'evidence',
            'outcomes',
            'events',
            'project',
            'recommendations',
        ]);

        return [
            'action' => $this->actionPresenter->present($action),

            'timeline' => $this->timelinePresenter->present($action),

            'assessment' => $action->outcomes
                ->map(
                    fn ($outcome) => $this->evaluator->evaluate($outcome)
                )
                ->values()
                ->all(),

            'priority' => $this->prioritiser->prioritise($action),

            'recommendations' => $action->recommendations
                ->map(
                    fn ($recommendation) => [
                        'type' => $recommendation->type,
                        'recommendation' => $recommendation->recommendation,
                        'expected_impact' => $recommendation->expected_impact,
                        'confidence' => $recommendation->confidence,
                        'status' => $recommendation->status,
                    ]
                )
                ->values()
                ->all(),
        ];
    }
}
