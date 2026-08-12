<?php

namespace App\Domains\CommercialTruth;

use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementEvidence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommercialAgreementInferenceService
{
    public function __construct(
        private BillingCadenceEngine $cadence
    ) {}

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
            ->select([
                'items.id as invoice_item_id',
                'items.description',
                'items.unit_price',
                'invoices.client_id',
                'invoices.invoice_date',
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
                ): CommercialAgreement {
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

                    $agreement =
                        CommercialAgreement::updateOrCreate(
                            [
                                'client_id' => $first->client_id,

                                'service_type' => 'hosting',

                                'service_key' => $serviceKey,
                            ],
                            [
                                'cadence' => $cadence['cadence'],

                                'status' => 'candidate',

                                'observed_value' => $cadence[
                                        'observed_value'
                                    ],

                                'monthly_equivalent' => $cadence[
                                        'monthly_equivalent'
                                    ],

                                'confidence' => $cadence[
                                        'confidence'
                                    ],

                                'source' => 'invoice_history',

                                'reason' => 'Commercial agreement inferred from hosting invoice cadence.',

                                'metadata' => [
                                    'observation_count' => $observations
                                        ->count(),
                                ],
                            ]
                        );

                    foreach (
                        $observations as $item
                    ) {
                        CommercialAgreementEvidence::updateOrCreate(
                            [
                                'commercial_agreement_id' => $agreement->id,

                                'type' => 'invoice_item',

                                'reference' => $item->invoice_item_id,
                            ],
                            [
                                'summary' => $item->description,

                                'observed_on' => $item->invoice_date,

                                'observed_value' => $item->unit_price,

                                'confidence' => 100,
                            ]
                        );
                    }

                    return $agreement->fresh(
                        'evidence'
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
