<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\BillingCadenceEngine;
use App\Domains\CommercialTruth\CommercialServiceFingerprint;
use App\Domains\CommercialTruth\DTO\ClientServiceCandidate;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClientServiceCandidateService
{
    public function __construct(
        private readonly CommercialServiceFingerprint $fingerprint,
        private readonly BillingCadenceEngine $cadence,
    ) {}

    /**
     * @return Collection<int, ClientServiceCandidate>
     */
    public function all(): Collection
    {
        return $this->candidates();
    }

    /**
     * @return Collection<int, ClientServiceCandidate>
     */
    public function forClient(
        Client $client
    ): Collection {
        return $this->candidates(
            (string) $client->id
        );
    }

    /**
     * @return Collection<int, ClientServiceCandidate>
     */
    private function candidates(
        ?string $clientId = null
    ): Collection {
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
            ->when(
                $clientId,
                fn ($query) => $query->where(
                    'invoices.client_id',
                    $clientId
                )
            )
            ->orderBy('invoices.invoice_date')
            ->orderBy('items.created_at')
            ->orderBy('items.id')
            ->select([
                'items.id as invoice_item_id',
                'items.description',
                'items.quantity',
                'items.unit_price',
                'items.net_amount',
                'invoices.client_id',
                'invoices.invoice_date',
                'clients.name as client_name',
            ])
            ->get()
            ->map(
                function (object $item): array {
                    $classification = $this->fingerprint
                        ->fingerprint(
                            (string) $item->description
                        );

                    return [
                        'invoice_item_id' => (string) $item->invoice_item_id,
                        'client_id' => (string) $item->client_id,
                        'client_name' => (string) $item->client_name,
                        'description' => (string) $item->description,
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'net_amount' => (float) $item->net_amount,
                        'invoice_date' => $item->invoice_date !== null
                            ? substr(
                                (string) $item->invoice_date,
                                0,
                                10
                            )
                            : null,
                        'service_type' => $classification['service_type'],
                        'service_hint' => $classification['service_hint'],
                        'fingerprint' => $classification['fingerprint'],
                        'commercial_treatment' => $classification[
                            'commercial_treatment'
                        ],

                        'commercial_components' => $classification[
                            'commercial_components'
                        ] ?? [],

                        'classification_confidence' => (int) $classification[
                            'classification_confidence'
                        ],
                        'group_key' => $this->groupKey(
                            $classification,
                            (string) $item->description,
                            (string) $item->invoice_item_id
                        ),
                    ];
                }
            );

        return $observations
            ->groupBy(
                fn (array $observation) => $observation['client_id']
                    .'|'
                    .$observation['group_key']
            )
            ->map(
                fn (Collection $group) => $this->candidate(
                    $group
                )
            )
            ->values();
    }

    private function candidate(
        Collection $observations
    ): ClientServiceCandidate {
        $first = $observations->first();

        /*
         * Cadence is evidence about billing dates, not line count.
         *
         * Multiple lines for the same candidate on one invoice date
         * must not introduce artificial zero-day intervals.
         */
        $cadenceObservations = $observations
            ->filter(
                fn (array $observation) => $observation[
                    'invoice_date'
                ] !== null
            )
            ->groupBy('invoice_date')
            ->map(
                fn (Collection $sameDate) => $sameDate->last()
            )
            ->map(
                fn (array $observation) => (object) [
                    'invoice_date' => $observation['invoice_date'],
                    'quantity' => $observation['quantity'],
                    'unit_price' => $observation['unit_price'],
                    'net_amount' => $observation['net_amount'],
                ]
            )
            ->values();

        $cadence = $this->cadence->infer(
            $cadenceObservations
        );

        $positive = $observations
            ->filter(
                fn (array $observation) => $observation[
                    'net_amount'
                ] > 0
            )
            ->sum('net_amount');

        $negative = $observations
            ->filter(
                fn (array $observation) => $observation[
                    'net_amount'
                ] < 0
            )
            ->sum('net_amount');

        $dates = $observations
            ->pluck('invoice_date')
            ->filter()
            ->sort()
            ->values();

        /*
         * Source observations are already ordered by invoice date,
         * item creation time and ID.
         */
        $latest = $observations->last();

        return new ClientServiceCandidate(
            clientId: $first['client_id'],
            clientName: $first['client_name'],
            serviceType: $first['service_type'],
            serviceHint: $first['service_hint'],
            fingerprint: $first['fingerprint'],
            commercialTreatment: $first[
                'commercial_treatment'
            ],
            evidenceCount: $observations->count(),
            invoiceItemIds: $observations
                ->pluck('invoice_item_id')
                ->values()
                ->all(),
            signedObservedNet: round(
                (float) $observations->sum(
                    'net_amount'
                ),
                2
            ),
            positiveObservedNet: round(
                (float) $positive,
                2
            ),
            negativeObservedNet: round(
                (float) $negative,
                2
            ),
            latestObservedUnitPrice: round(
                (float) $latest['unit_price'],
                2
            ),
            firstObservedOn: $dates->first(),
            lastObservedOn: $dates->last(),
            cadence: $cadence['cadence'],
            monthlyEquivalent: (float) $cadence[
                'monthly_equivalent'
            ],
            classificationConfidence: (int) $observations
                ->min(
                    'classification_confidence'
                ),
            cadenceConfidence: (int) $cadence[
                'confidence'
            ],
            commercialComponents: $first[
                'commercial_components'
            ],
        );
    }

    private function groupKey(
        array $classification,
        string $description,
        string $invoiceItemId
    ): string {
        /*
         * Composite evidence is source-item atomic.
         *
         * Two invoice lines with identical wording may later
         * require different human decompositions. They must not
         * silently collapse into one candidate merely because
         * their descriptions or component families match.
         */
        if (
            $classification[
                'commercial_treatment'
            ] === 'composite_candidate'
        ) {
            return hash(
                'sha256',
                implode('|', [
                    'composite_evidence',
                    $invoiceItemId,
                ])
            );
        }

        /*
         * Recurring/managed services may legitimately collapse
         * across repeated invoice wording via the fingerprint.
         *
         * Projects, pass-through costs and unknown evidence must
         * not all collapse merely because they share a broad
         * classification.
         */
        if (
            in_array(
                $classification[
                    'commercial_treatment'
                ],
                [
                    'service_candidate',
                    'managed_service_candidate',
                ],
                true
            )
        ) {
            return $classification[
                'fingerprint'
            ];
        }

        $descriptionKey = Str::of(
            $description
        )
            ->lower()
            ->squish()
            ->toString();

        return hash(
            'sha256',
            implode('|', [
                $classification['service_type'],
                $descriptionKey,
            ])
        );
    }
}
