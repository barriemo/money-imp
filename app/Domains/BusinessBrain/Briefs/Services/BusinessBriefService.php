<?php

namespace App\Domains\BusinessBrain\Briefs\Services;

use App\Domains\BusinessBrain\Actions\ExecutiveActionClassifier;
use App\Domains\BusinessBrain\Briefs\BusinessBrief;
use App\Domains\BusinessBrain\Organisation\Services\BusinessProfileService;
use App\Models\ExecutiveAction;

class BusinessBriefService
{
    public function __construct(
        private BusinessProfileService $profile,

        private ExecutiveActionClassifier $classifier
    ) {}

    public function current(): BusinessBrief
    {
        $business =
            $this->profile->current();

        $actions =
            ExecutiveAction::query()
                ->where(
                    'status',
                    'pending'
                )
                ->orderByDesc(
                    'score'
                )
                ->get()
                ->filter(
                    fn (ExecutiveAction $action) => $this->classifier
                        ->isExecutive($action->type)
                )
                ->take(5)
                ->map(
                    fn (ExecutiveAction $action) => [
                        'title' => $action->title,

                        'description' => $action->description,

                        'priority' => $action->score,

                        'recommended_action' => $action
                            ->recommended_action,
                    ]
                )
                ->values()
                ->toArray();

        return new BusinessBrief(
            business: $business->name,

            priorities: $business->priorities,

            actions: $actions
        );
    }
}
