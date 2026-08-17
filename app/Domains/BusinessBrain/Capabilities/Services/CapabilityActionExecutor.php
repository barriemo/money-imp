<?php

namespace App\Domains\BusinessBrain\Capabilities\Services;

use App\Models\CapabilityAction;
use App\Models\ExecutiveAction;

class CapabilityActionExecutor
{
    public function execute(
        CapabilityAction $action
    ): array {
        $executiveAction = ExecutiveAction::firstOrCreate(
            [
                'fingerprint' => $this->fingerprint($action),
            ],
            [
                'type' => $action->capability->name,
                'title' => $action->name,
                'description' => $action->description
                    ?? $action->name,
                'recommended_action' => $action->name,
                'confidence' => 80,
                'urgency' => 50,
                'score' => 65,
                'status' => 'pending',
                'capability_definition_id' => $action->capability_definition_id,
            ]
        );

        return [
            'action' => $executiveAction,
            'created' => $executiveAction->wasRecentlyCreated,
        ];
    }

    protected function fingerprint(
        CapabilityAction $action
    ): string {
        return sha1(
            implode('|', [
                $action->capability_definition_id,
                $action->name,
            ])
        );
    }
}