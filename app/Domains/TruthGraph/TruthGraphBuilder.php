<?php

namespace App\Domains\TruthGraph;

use App\Domains\CommercialTruth\Graph\CommercialTruthGraphProvider;
use App\Domains\CommercialTruth\Recovery\Graph\WorkRecoveryGraphProvider;
use App\Domains\Infrastructure\Graph\InfrastructureTruthGraphProvider;
use App\Domains\RevenueTruth\Graph\RevenueTruthGraphProvider;
use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\WorkIntelligence\Graph\WorkEvidenceGraphProvider;
use App\Models\Client;

class TruthGraphBuilder
{
    public function __construct(
        private CommercialTruthGraphProvider $commercialTruth,
        private InfrastructureTruthGraphProvider $infrastructureTruth,
        private RevenueTruthGraphProvider $revenueTruth,
        private WorkEvidenceGraphProvider $workEvidence,
        private WorkRecoveryGraphProvider $workRecovery
    ) {}

    public function buildForClient(
        Client $client
    ): array {
        $root =
            new TruthGraphNode(
                type: 'client',

                id: $client->id,

                label: $client->name,

                attributes: [
                    'status' => $client->status,
                ],

                confidence: 100
            );

        $graph =
            new TruthGraphContribution(
                nodes: collect([
                    $root,
                ]),

                edges: collect()
            );

        foreach (
            $this->providers() as $provider
        ) {
            if (
                ! $provider->supports(
                    'client'
                )
            ) {
                continue;
            }

            $graph =
                $graph->merge(
                    $provider->build(
                        $client->id
                    )
                );
        }

        return [
            'root' => $root->key(),

            'nodes' => $graph->nodes,

            'edges' => $graph->edges,
        ];
    }

    /**
     * @return array<int, TruthGraphProvider>
     */
    private function providers(): array
    {
        return [
            $this->commercialTruth,
            $this->infrastructureTruth,
            $this->workEvidence,
            $this->workRecovery,
            $this->revenueTruth,
        ];
    }
}
