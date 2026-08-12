<?php

namespace App\Domains\WorkIntelligence\Graph;

use App\Domains\Evidence\EvidenceRepository;
use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;

class WorkEvidenceGraphProvider implements TruthGraphProvider
{
    public function __construct(
        private EvidenceRepository $repository
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

        $evidence =
            $this->repository
                ->all()
                ->filter(
                    fn ($item) => (
                        $item->type === 'work_log'
                        &&
                        ($item->metadata['client_id'] ?? null)
                            === $rootId
                    )
                );

        foreach ($evidence as $item) {
            $workLogId =
                $item->metadata['work_log_id'];

            $workNode =
                new TruthGraphNode(
                    type: 'work_log',
                    id: $workLogId,
                    label: $item->summary,
                    attributes: [
                        'minutes' => $item->metadata['minutes'],

                        'commercial_value' => $item->metadata['commercial_value'],
                    ],
                    confidence: $item->confidence
                );

            $evidenceNode =
                new TruthGraphNode(
                    type: 'evidence',
                    id: sha1(
                        $item->summary
                    ),
                    label: 'Evidence: '.$item->summary,
                    attributes: [
                        'source' => $item->source,
                    ],
                    confidence: $item->confidence
                );

            $nodes->push(
                $workNode,
                $evidenceNode
            );

            $edges->push(
                new TruthGraphEdge(
                    from: 'client:'.$rootId,
                    to: $workNode->key(),
                    relationship: 'received_work',
                    confidence: $item->confidence
                ),

                new TruthGraphEdge(
                    from: $workNode->key(),
                    to: $evidenceNode->key(),
                    relationship: 'supported_by',
                    confidence: $item->confidence
                )
            );
        }

        return new TruthGraphContribution(
            nodes: $nodes,
            edges: $edges
        );
    }
}
