<?php

namespace App\Domains\BusinessBrain\Actions;

use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;
use App\Models\ExecutiveAction;

class ExecutiveActionFactory
{
    public function __construct(
        private ExecutiveActionFingerprint $fingerprints
    ) {}

    public function createOrRefresh(
        ExecutiveReasoning $reasoning
    ): ExecutiveAction {
        $fingerprint =
            $this->fingerprints
                ->make(
                    $reasoning
                );

        $existing =
            ExecutiveAction::query()
                ->where(
                    'fingerprint',
                    $fingerprint
                )
                ->first();

        if (
            $existing
            && in_array(
                $existing->status,
                [
                    'completed',
                    'verified',
                    'archived',
                ],
                true
            )
        ) {
            return $existing;
        }

        return ExecutiveAction::updateOrCreate(
            [
                'fingerprint' => $fingerprint,
            ],
            [
                'client_id' => $reasoning->clientId,

                'client' => $reasoning->client,

                'type' => $reasoning->type,

                'title' => $reasoning->title,

                'description' => $reasoning->description,

                'recommended_action' => $reasoning
                    ->recommendedAction,

                'estimated_financial_impact' => $reasoning
                    ->estimatedFinancialImpact,

                'estimated_effort_minutes' => $reasoning
                    ->estimatedEffortMinutes,

                'confidence' => $reasoning->confidence,

                'urgency' => $reasoning->urgency,

                'score' => $reasoning->score,

                'evidence' => $reasoning
                    ->supportingEvidence,

                'metadata' => [
                    'source' => 'executive_reasoning',
                    'last_reasoned_at' => now()
                        ->toIso8601String(),
                ],
            ]
        );
    }
}
