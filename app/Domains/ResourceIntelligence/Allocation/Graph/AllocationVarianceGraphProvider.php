<?php

namespace App\Domains\ResourceIntelligence\Allocation\Graph;

use App\Domains\ResourceIntelligence\Allocation\AllocationVarianceRepository;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummariser;
use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;

class AllocationVarianceGraphProvider implements TruthGraphProvider
{
    public function __construct(
        private AllocationVarianceRepository $repository,
        private AllocationVarianceSummariser $summariser
    ) {}

    public function supports(
        string $rootType
    ): bool {
        return $rootType === 'client';
    }

    public function build(
        string $rootId
    ): TruthGraphContribution {
        $summary =
            $this->summariser->summarise(
                $this->repository->findForClient(
                    $rootId
                )
            );

        $varianceNode =
            new TruthGraphNode(
                type: 'allocation_variance',

                id: $rootId,

                label: 'Resource allocation variance',

                attributes: [
                    'overrun_hours' => $summary->totalOverrunHours,

                    'cost_exposure' => $summary->totalCostExposure,

                    'highest_risk_resource' => $summary->highestRiskResource,
                ],

                confidence: 90
            );

        return new TruthGraphContribution(
            nodes: collect([
                $varianceNode,
            ]),

            edges: collect([
                new TruthGraphEdge(
                    from: 'client:'.$rootId,
                    to: $varianceNode->key(),
                    relationship: 'has_resource_variance',
                    confidence: 90
                ),
            ])
        );
    }
}
