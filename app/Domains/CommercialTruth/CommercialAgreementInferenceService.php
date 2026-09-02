<?php

namespace App\Domains\CommercialTruth;

use App\Domains\CommercialTruth\DTO\CommercialAgreementCandidate;
use App\Domains\CommercialTruth\Services\CanonicalBillingEvidenceStatusPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommercialAgreementInferenceService
{
    public function __construct(
        private BillingCadenceEngine $cadence,
        private CanonicalBillingEvidenceStatusPolicy $statusPolicy,
    ) {}

    /**
     * Invoice history may suggest commercial agreement candidates.
     *
     * This service is deliberately read only.
     *
     * An inferred billing pattern is evidence for human review;
     * it is never itself contracted commercial truth.
     *
     * @return Collection<int, CommercialAgreementCandidate>
     */
    public function inferHosting(): Collection
    {
        $items = DB::table(
            'accounting_invoice_items as items'
        )
            ->join(
                'accounting_invoices as invoices',
                'invoices.id',
                '=',
                'items.accounting_invoice_id'
            )
            ->where(
                'items.description',
                'like',
                '%hosting%'
            )
            ->whereIn(
                'invoices.status',
                $this->statusPolicy
                    ->admissibleStatuses()
            )
            ->select([
                'items.id as invoice_item_id',
                'items.description',
                'items.unit_price',
                'items.net_amount',
                'invoices.client_id',
                'invoices.invoice_date',
                'invoices.status as invoice_status',
            ])
            ->get();

        return $items
            ->groupBy(
                fn (object $item) => $item->client_id
                    .'|'
                    .$this->serviceKey(
                        $item->description
                    )
            )
            ->map(
                function (
                    Collection $observations
                ): CommercialAgreementCandidate {
                    $first =
                        $observations->first();

                    $serviceKey =
                        $this->serviceKey(
                            $first->description
                        );

                    $cadence =
                        $this->cadence
                            ->infer(
                                $observations
                            );

                    return new CommercialAgreementCandidate(
                        clientId: (string) $first->client_id,

                        serviceType: 'hosting',

                        serviceKey: $serviceKey,

                        cadence: $cadence['cadence'],

                        observedValue: round(
                            (float) $cadence[
                                'observed_value'
                            ],
                            2
                        ),

                        monthlyEquivalent: round(
                            (float) $cadence[
                                'monthly_equivalent'
                            ],
                            2
                        ),

                        confidence: (int) $cadence[
                                'confidence'
                            ],

                        source: 'invoice_history',

                        reason: 'Possible commercial agreement inferred from admissible hosting invoice cadence; human contractual confirmation is required.',

                        evidence: $observations
                            ->map(
                                fn (object $item) => [
                                    'type' => 'invoice_item',

                                    'reference' => (string) $item
                                        ->invoice_item_id,

                                    'summary' => $item
                                        ->description,

                                    'observed_on' => $item
                                        ->invoice_date,

                                    'observed_value' => round(
                                        (float) $item
                                            ->unit_price,
                                        2
                                    ),

                                    'invoice_status' => $item
                                        ->invoice_status,

                                    'confidence' => 100,
                                ]
                            )
                            ->values()
                            ->all(),
                    );
                }
            )
            ->values();
    }

    private function serviceKey(
        string $description
    ): string {
        $text =
            Str::of(
                $description
            )
                ->lower()
                ->replace(
                    [
                        'monthly',
                        'annual',
                        'yearly',
                    ],
                    ''
                )
                ->squish()
                ->toString();

        return hash(
            'sha256',
            $text
        );
    }
}
