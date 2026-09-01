<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\CommercialServiceFingerprint;
use App\Domains\CommercialTruth\DTO\ClientServiceAttributionCandidate;
use App\Models\Client;
use App\Models\ClientServiceReconciliation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ClientServiceAttributionCandidateService
{
    public function __construct(
        private readonly CommercialServiceFingerprint $fingerprint,
    ) {}

    /**
     * @return Collection<int, ClientServiceAttributionCandidate>
     */
    public function all(): Collection
    {
        return $this->candidates();
    }

    /**
     * @return Collection<int, ClientServiceAttributionCandidate>
     */
    public function forClient(
        Client $client
    ): Collection {
        return $this->candidates(
            (string) $client->id
        );
    }

    /**
     * Candidates with one unambiguous prior human-confirmed
     * canonical service identity.
     *
     * @return Collection<int, ClientServiceAttributionCandidate>
     */
    public function ready(): Collection
    {
        return $this->all()
            ->filter(
                fn (
                    ClientServiceAttributionCandidate $candidate
                ) => $candidate->isReadyForReview()
            )
            ->values();
    }

    private function candidates(
        ?string $clientId = null
    ): Collection {
        /*
         * Only NEW / unattributed evidence belongs here.
         *
         * Historic evidence already attached to a ClientService
         * remains available elsewhere as canonical attribution
         * history and must not be proposed again.
         */
        $observations = DB::table(
            'accounting_invoice_items as items'
        )
            ->join(
                'accounting_invoices as invoices',
                'invoices.id',
                '=',
                'items.accounting_invoice_id'
            )
            ->join(
                'clients',
                'clients.id',
                '=',
                'invoices.client_id'
            )
            ->whereNull(
                'items.client_service_id'
            )
            ->when(
                $clientId,
                fn ($query) => $query->where(
                    'invoices.client_id',
                    $clientId
                )
            )
            ->orderBy(
                'invoices.invoice_date'
            )
            ->orderBy(
                'items.created_at'
            )
            ->orderBy(
                'items.id'
            )
            ->select([
                'items.id as invoice_item_id',
                'items.description',
                'items.net_amount',
                'invoices.client_id',
                'invoices.invoice_date',
                'clients.name as client_name',
            ])
            ->get()
            ->map(
                function (object $item): array {
                    $classification =
                        $this->fingerprint
                            ->fingerprint(
                                (string) $item
                                    ->description
                            );

                    return [
                        'invoice_item_id' => (string) $item
                            ->invoice_item_id,

                        'client_id' => (string) $item
                            ->client_id,

                        'client_name' => (string) $item
                            ->client_name,

                        'net_amount' => (float) $item
                            ->net_amount,

                        'invoice_date' => $item->invoice_date !== null
                                ? substr(
                                    (string) $item
                                        ->invoice_date,
                                    0,
                                    10
                                )
                                : null,

                        'service_type' => $classification[
                                'service_type'
                            ],

                        'service_hint' => $classification[
                                'service_hint'
                            ],

                        'fingerprint' => $classification[
                                'fingerprint'
                            ],

                        'commercial_treatment' => $classification[
                                'commercial_treatment'
                            ],
                    ];
                }
            )
            ->filter(
                fn (array $row) => in_array(
                    $row[
                        'commercial_treatment'
                    ],
                    [
                        'service_candidate',
                        'managed_service_candidate',
                    ],
                    true
                )
            )
            ->values();

        $mappings =
            ClientServiceReconciliation::query()
                ->with(
                    'clientService'
                )
                ->whereIn(
                    'decision',
                    [
                        'confirmed',
                        'merged',
                    ]
                )
                ->whereNotNull(
                    'client_service_id'
                )
                ->when(
                    $clientId,
                    fn ($query) => $query->where(
                        'client_id',
                        $clientId
                    )
                )
                ->orderBy(
                    'reviewed_at'
                )
                ->orderBy(
                    'created_at'
                )
                ->get()
                ->groupBy(
                    fn (
                        ClientServiceReconciliation $row
                    ) => $row->client_id
                        .'|'
                        .$row->candidate_fingerprint
                );

        return $observations
            ->groupBy(
                fn (array $row) => $row['client_id']
                    .'|'
                    .$row['fingerprint']
            )
            ->map(
                function (
                    Collection $rows,
                    string $key
                ) use (
                    $mappings
                ): ClientServiceAttributionCandidate {
                    $first =
                        $rows->first();

                    $mappingRows =
                        $mappings->get(
                            $key,
                            collect()
                        );

                    $validMappings =
                        $mappingRows
                            ->filter(
                                fn (
                                    ClientServiceReconciliation $row
                                ) => $row->clientService !== null
                                    && $row
                                        ->clientService
                                        ->client_id
                                        === $first[
                                            'client_id'
                                        ]
                            )
                            ->values();

                    $candidateServiceIds =
                        $validMappings
                            ->pluck(
                                'client_service_id'
                            )
                            ->filter()
                            ->map(
                                fn ($id) => (string) $id
                            )
                            ->unique()
                            ->sort()
                            ->values()
                            ->all();

                    $activeMappings =
                        $validMappings
                            ->filter(
                                fn (
                                    ClientServiceReconciliation $row
                                ) => $row
                                    ->clientService
                                    ->status
                                    === 'active'
                            )
                            ->values();

                    $activeServiceIds =
                        $activeMappings
                            ->pluck(
                                'client_service_id'
                            )
                            ->filter()
                            ->map(
                                fn ($id) => (string) $id
                            )
                            ->unique()
                            ->sort()
                            ->values()
                            ->all();

                    $matchStatus =
                        match (true) {
                            count(
                                $candidateServiceIds
                            ) > 1 => 'ambiguous',

                            count(
                                $candidateServiceIds
                            ) === 1
                            && count(
                                $activeServiceIds
                            ) === 0 => 'inactive_target',

                            count(
                                $activeServiceIds
                            ) === 1 => 'matched',

                            default => 'unmatched',
                        };

                    $matchedService =
                        $matchStatus === 'matched'
                            ? $activeMappings
                                ->first()
                                ?->clientService
                            : null;

                    $dates =
                        $rows
                            ->pluck(
                                'invoice_date'
                            )
                            ->filter()
                            ->sort()
                            ->values();

                    return new ClientServiceAttributionCandidate(
                        clientId: $first['client_id'],

                        clientName: $first['client_name'],

                        candidateFingerprint: $first['fingerprint'],

                        serviceType: $first['service_type'],

                        serviceHint: $first['service_hint'],

                        invoiceItemIds: $rows
                            ->pluck(
                                'invoice_item_id'
                            )
                            ->values()
                            ->all(),

                        evidenceCount: $rows->count(),

                        signedObservedNet: round(
                            (float) $rows
                                ->sum(
                                    'net_amount'
                                ),
                            2
                        ),

                        firstObservedOn: $dates->first(),

                        lastObservedOn: $dates->last(),

                        matchStatus: $matchStatus,

                        clientServiceId: $matchedService !== null
                                ? (string) $matchedService
                                    ->id
                                : null,

                        clientServiceName: $matchedService?->name,

                        candidateClientServiceIds: $candidateServiceIds,

                        supportingReconciliationIds: $validMappings
                            ->pluck('id')
                            ->map(
                                fn ($id) => (string) $id
                            )
                            ->sort()
                            ->values()
                            ->all(),
                    );
                }
            )
            ->values();
    }
}
