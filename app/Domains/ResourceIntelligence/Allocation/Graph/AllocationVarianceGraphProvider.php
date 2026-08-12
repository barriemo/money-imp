<?php

namespace App\Domains\ResourceIntelligence\Allocation\Graph;

use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;
use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;

class AllocationVarianceGraphProvider implements TruthGraphProvider
{
    public function __construct(
        private AllocationVarianceSummary $summary
    ) {}

    public function supports(
        string $rootType
    ): bool {
        return $rootType === 'client';
    }

    public function build(
        string $rootId
    ): TruthGraphContribution {
        $varianceNode =
            new TruthGraphNode(
                type: 'allocation_variance',

                id: $rootId,

                label: 'Resource allocation variance',

                attributes: [
                    'overrun_hours' => $this->summary->totalOverrunHours,

                    'cost_exposure' => $this->summary->totalCostExposure,

                    'highest_risk_resource' => $this->summary->highestRiskResource,
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
