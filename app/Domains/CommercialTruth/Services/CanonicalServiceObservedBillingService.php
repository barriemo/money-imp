<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\BillingCadenceEngine;
use App\Domains\CommercialTruth\DTO\CanonicalServiceObservedBilling;
use App\Models\Client;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CanonicalServiceObservedBillingService
{
    public function __construct(
        private readonly BillingCadenceEngine $cadence,
        private readonly BillingEvidenceAssessmentService $billingEvidence,
    ) {}

    /**
     * @return Collection<int, CanonicalServiceObservedBilling>
     */
    public function all(
        ?CarbonImmutable $asOf = null
    ): Collection {
        return $this->observedBilling(
            asOf: $asOf
        );
    }

    /**
     * @return Collection<int, CanonicalServiceObservedBilling>
     */
    public function forClient(
        Client $client,
        ?CarbonImmutable $asOf = null
    ): Collection {
        return $this->observedBilling(
            clientId: (string) $client->id,
            asOf: $asOf
        );
    }

    public function forService(
        ClientService $service,
        ?CarbonImmutable $asOf = null
    ): ?CanonicalServiceObservedBilling {
        return $this->observedBilling(
            clientServiceId: (string) $service->id,
            asOf: $asOf
        )->first();
    }

    private function observedBilling(
        ?string $clientId = null,
        ?string $clientServiceId = null,
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??=
            CarbonImmutable::today();

        $observations =
            DB::table(
                'accounting_invoice_items as items'
            )
                ->join(
                    'accounting_invoices as invoices',
                    'invoices.id',
                    '=',
                    'items.accounting_invoice_id'
                )
                ->join(
                    'client_services as services',
                    'services.id',
                    '=',
                    'items.client_service_id'
                )
                ->join(
                    'clients',
                    'clients.id',
                    '=',
                    'services.client_id'
                )
                ->whereNotNull(
                    'items.client_service_id'
                )
                ->whereNull(
                    'services.deleted_at'
                )
                ->when(
                    $clientId,
                    fn ($query) => $query->where(
                        'services.client_id',
                        $clientId
                    )
                )
                ->when(
                    $clientServiceId,
                    fn ($query) => $query->where(
                        'services.id',
                        $clientServiceId
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
                    'items.unit_price',
                    'items.net_amount',
                    'items.created_at',
                    'invoices.invoice_date',
                    'services.id as client_service_id',
                    'services.client_id',
                    'services.name as service_name',
                    'services.status as service_status',
                    'clients.name as client_name',
                ])
                ->get();

        return $observations
            ->groupBy(
                'client_service_id'
            )
            ->map(
                fn (
                    Collection $rows
                ) => $this->build(
                    $rows,
                    $asOf
                )
            )
            ->values();
    }

    private function build(
        Collection $rows,
        CarbonImmutable $asOf
    ): CanonicalServiceObservedBilling {
        $first =
            $rows->first();

        /*
         * Cadence concerns billing dates, not line count.
         *
         * Multiple attributed lines on the same invoice date
         * must not create zero-day cadence intervals.
         */
        $cadenceObservations =
            $rows
                ->filter(
                    fn (object $row) => $row->invoice_date
                        !== null
                )
                ->groupBy(
                    fn (object $row) => substr(
                        (string) $row
                            ->invoice_date,
                        0,
                        10
                    )
                )
                ->map(
                    fn (
                        Collection $sameDate
                    ) => $sameDate->last()
                )
                ->map(
                    fn (object $row) => (object) [
                        'invoice_date' => substr(
                            (string) $row
                                ->invoice_date,
                            0,
                            10
                        ),

                        'unit_price' => (float) $row
                            ->unit_price,
                    ]
                )
                ->values();

        $cadence =
            $this->cadence
                ->infer(
                    $cadenceObservations
                );

        $dates =
            $rows
                ->pluck(
                    'invoice_date'
                )
                ->filter()
                ->map(
                    fn ($date) => substr(
                        (string) $date,
                        0,
                        10
                    )
                )
                ->sort()
                ->values();

        $lastObservedOn =
            $dates->last();

        $billingEvidence =
            $this->billingEvidence
                ->assess(
                    cadence: $cadence[
                            'cadence'
                        ],
                    cadenceConfidence: (int) $cadence[
                            'confidence'
                        ],
                    lastObservedOn: $lastObservedOn,
                    monthlyEquivalent: (float) $cadence[
                            'monthly_equivalent'
                        ],
                    asOf: $asOf
                );

        $latest =
            $rows->last();

        return new CanonicalServiceObservedBilling(
            clientServiceId: (string) $first
                ->client_service_id,

            clientId: (string) $first
                ->client_id,

            clientName: (string) $first
                ->client_name,

            serviceName: (string) $first
                ->service_name,

            serviceStatus: (string) $first
                ->service_status,

            evidenceCount: $rows->count(),

            invoiceItemIds: $rows
                ->pluck(
                    'invoice_item_id'
                )
                ->map(
                    fn ($id) => (string) $id
                )
                ->values()
                ->all(),

            signedObservedNet: round(
                (float) $rows->sum(
                    'net_amount'
                ),
                2
            ),

            latestObservedUnitPrice: round(
                (float) $latest
                    ->unit_price,
                2
            ),

            firstObservedOn: $dates->first(),

            lastObservedOn: $lastObservedOn,

            cadence: $cadence[
                    'cadence'
                ],

            monthlyEquivalent: (float) $cadence[
                    'monthly_equivalent'
                ],

            cadenceConfidence: (int) $cadence[
                    'confidence'
                ],

            daysSinceLastObservation: $billingEvidence
                ->daysSinceLastObservation,

            freshness: $billingEvidence
                ->freshness,

            recurringEvidence: $billingEvidence
                ->recurringEvidence,

            currentMonthlyEquivalent: $billingEvidence
                ->currentMonthlyEquivalent,
        );
    }
}
