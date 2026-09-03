<?php

namespace App\Domains\CommercialTruth\Graph;

use App\Domains\TruthGraph\Contracts\TruthGraphProvider;
use App\Domains\TruthGraph\TruthGraphContribution;
use App\Domains\TruthGraph\TruthGraphEdge;
use App\Domains\TruthGraph\TruthGraphNode;
use App\Models\Client;
use App\Models\CommercialAgreement;

class CommercialTruthGraphProvider implements TruthGraphProvider
{
    public function supports(
        string $rootType
    ): bool {
        return $rootType === 'client';
    }

    public function build(
        string $rootId
    ): TruthGraphContribution {
        $client =
            Client::query()
                ->find(
                    $rootId
                );

        if (! $client) {
            return TruthGraphContribution::empty();
        }

        $nodes = collect();
        $edges = collect();

        $clientKey =
            'client:'
            .$client->id;

        /*
         * CommercialAgreement is now an immutable contractual
         * assertion tied to canonical ClientService truth.
         *
         * The graph deliberately includes agreement history rather
         * than collapsing it into invoice-derived service inference.
         */
        $agreements =
            CommercialAgreement::query()
                ->with([
                    'clientService',
                    'supersededBy',
                ])
                ->where(
                    'client_id',
                    $client->id
                )
                ->orderBy(
                    'created_at'
                )
                ->get();

        foreach (
            $agreements as $agreement
        ) {
            $service =
                $agreement->clientService;

            /*
             * The database FK should make this impossible.
             *
             * Do not invent a label or service identity if canonical
             * truth is unexpectedly unavailable.
             */
            if ($service === null) {
                continue;
            }

            $node =
                new TruthGraphNode(
                    type: 'commercial_agreement',

                    id: $agreement->id,

                    label: $service->name
                        .' agreement',

                    attributes: [
                        'client_id' => $agreement->client_id,

                        'client_service_id' => $agreement
                            ->client_service_id,

                        'client_service_name' => $service->name,

                        'status' => $agreement->status,

                        'cadence' => $agreement->cadence,

                        'contracted_amount_pence' => $agreement
                            ->contracted_amount_pence,

                        'currency' => $agreement->currency,

                        /*
                         * Critical:
                         *
                         * null means there is no recurring monthly
                         * equivalent for this assertion. Do not cast
                         * null to 0.0.
                         */
                        'monthly_equivalent' => $agreement
                            ->monthly_equivalent
                                !== null
                                    ? (float) $agreement
                                        ->monthly_equivalent
                                    : null,

                        'effective_from' => $agreement
                            ->effective_from
                            ?->toDateString(),

                        'effective_to' => $agreement
                            ->effective_to
                            ?->toDateString(),

                        'renews_on' => $agreement
                            ->renews_on
                            ?->toDateString(),

                        'source' => $agreement->source,

                        'source_reference' => $agreement
                            ->source_reference,

                        'reviewed_by_name' => $agreement
                            ->reviewed_by_name,

                        'reviewed_at' => $agreement
                            ->reviewed_at
                            ?->toIso8601String(),

                        'supersedes_commercial_agreement_id' => $agreement
                            ->supersedes_commercial_agreement_id,

                        'has_successor' => $agreement
                            ->supersededBy
                                !== null,
                    ],

                    /*
                     * A persisted agreement assertion is explicitly
                     * human-confirmed contractual truth.
                     *
                     * Evidence confidence belongs to evidence rows,
                     * not to the agreement assertion itself.
                     */
                    confidence: 100
                );

            $nodes->push(
                $node
            );

            $edges->push(
                new TruthGraphEdge(
                    from: $clientKey,

                    to: $node->key(),

                    relationship: 'has_agreement',

                    confidence: 100,

                    evidence: [
                        'human_confirmed_contract_truth',
                    ]
                )
            );
        }

        return new TruthGraphContribution(
            nodes: $nodes,

            edges: $edges
        );
    }
}
