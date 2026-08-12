<?php

namespace App\Domains\CommercialTruth\Recovery\Graph;

use App\Domains\CommercialTruth\Recovery\WorkRecoveryReasoner;
use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;
use App\Models\WorkLog;

class WorkRecoveryGraphProvider implements TruthGraphProvider
{
    public function __construct(
        private WorkRecoveryReasoner $reasoner
    ) {}

    public function supports(
        string $rootType
    ): bool {
        return $rootType === 'client';
    }

    public function build(
        string $rootId
    ): TruthGraphContribution {
        $nodes = collect();

        $edges = collect();

        WorkLog::query()
            ->where('client_id', $rootId)
            ->get()
            ->each(function (WorkLog $workLog) use (
                $nodes,
                $edges,
                $rootId
            ): void {
                $assessment =
                    $this->reasoner->assess(
                        $workLog
                    );

                $recoveryNode =
                    new TruthGraphNode(
                        type: 'work_recovery',
                        id: $workLog->id,
                        label: $assessment->state,
                        attributes: [
                            'value' => $assessment->value,
                            'reason' => $assessment->reason,
                        ],
                        confidence: $assessment->confidence
                    );

                $nodes->push(
                    $recoveryNode
                );

                $edges->push(
                    new TruthGraphEdge(
                        from: 'client:'.$rootId,
                        to: $recoveryNode->key(),
                        relationship: 'has_recovery_state',
                        confidence: $assessment->confidence
                    )
                );
            });

        return new TruthGraphContribution(
            nodes: $nodes,
            edges: $edges
        );
    }
}
