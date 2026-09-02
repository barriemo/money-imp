<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\CanonicalBillingObservation;
use App\Models\CommercialEvidenceAllocationSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CanonicalBillingObservationService
{
    public function __construct(
        private readonly CanonicalBillingEvidenceStatusPolicy $statusPolicy,
        private readonly CompositeCommercialEvidenceFingerprint $evidenceFingerprint,
    ) {}

    /**
     * Return commercially admissible billing observations after
     * normalising both supported attribution mechanisms:
     *
     * 1. direct accounting_invoice_items.client_service_id
     * 2. reviewed commercial allocation ledger evidence
     *
     * @return Collection<int, CanonicalBillingObservation>
     */
    public function all(
        ?string $clientId = null,
        ?string $clientServiceId = null
    ): Collection {
        $direct =
            $this->directObservations(
                clientId: $clientId,
                clientServiceId: $clientServiceId
            );

        $allocated =
            $this->allocationObservations(
                clientId: $clientId,
                clientServiceId: $clientServiceId
            );

        /*
         * One exact source item may have one live attribution
         * interpretation only.
         *
         * A stale historical allocation is ignored before reaching
         * this point. A current allocation plus direct attribution
         * is conflicting canonical truth and must fail loudly.
         */
        $directIds =
            $direct
                ->pluck('invoice_item_id')
                ->unique()
                ->values();

        $allocatedIds =
            $allocated
                ->pluck('invoice_item_id')
                ->unique()
                ->values();

        $overlap =
            $directIds
                ->intersect(
                    $allocatedIds
                )
                ->values();

        if ($overlap->isNotEmpty()) {
            throw new LogicException(
                sprintf(
                    'Canonical billing evidence has conflicting direct and allocation attribution for invoice item(s): %s',
                    $overlap->implode(', ')
                )
            );
        }

        /*
         * Preserve the existing deterministic source ordering.
         *
         * CanonicalServiceObservedBillingService deliberately keeps
         * its existing same-service/same-date cadence treatment.
         * Stage 2B does not reinterpret historic catch-up billing.
         */
        return $direct
            ->concat(
                $allocated
            )
            ->sort(
                function (
                    CanonicalBillingObservation $left,
                    CanonicalBillingObservation $right
                ): int {
                    foreach ([
                        'invoice_date',
                        'created_at',
                        'invoice_item_id',
                        'attribution_source',
                        'allocation_id',
                    ] as $field) {
                        $comparison =
                            strcmp(
                                (string) (
                                    $left->{$field}
                                    ?? ''
                                ),
                                (string) (
                                    $right->{$field}
                                    ?? ''
                                )
                            );

                        if ($comparison !== 0) {
                            return $comparison;
                        }
                    }

                    return 0;
                }
            )
            ->values();
    }

    /**
     * @return Collection<int, CanonicalBillingObservation>
     */
    private function directObservations(
        ?string $clientId,
        ?string $clientServiceId
    ): Collection {
        return DB::table(
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
            ->whereIn(
                'invoices.status',
                $this->statusPolicy
                    ->admissibleStatuses()
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
                'items.quantity',
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
            ->get()
            ->map(
                fn (object $row) => new CanonicalBillingObservation(
                    invoice_item_id: (string) $row->invoice_item_id,

                    quantity: (float) $row->quantity,

                    unit_price: (float) $row->unit_price,

                    net_amount: (float) $row->net_amount,

                    created_at: $this->nullableString(
                        $row->created_at
                    ),

                    invoice_date: $this->nullableString(
                        $row->invoice_date
                    ),

                    client_service_id: (string) $row->client_service_id,

                    client_id: (string) $row->client_id,

                    service_name: (string) $row->service_name,

                    service_status: (string) $row->service_status,

                    client_name: (string) $row->client_name,

                    attribution_source: 'direct',
                )
            )
            ->values();
    }

    /**
     * @return Collection<int, CanonicalBillingObservation>
     */
    private function allocationObservations(
        ?string $clientId,
        ?string $clientServiceId
    ): Collection {
        $sets =
            CommercialEvidenceAllocationSet::query()
                ->with([
                    'review',
                    'client',
                    'invoiceItem.invoice',

                    'allocations.service' => fn ($query) => $query
                        ->withTrashed()
                        ->with('client'),
                ])
                ->when(
                    $clientId,
                    fn ($query) => $query->where(
                        'client_id',
                        $clientId
                    )
                )
                ->when(
                    $clientServiceId,
                    fn ($query) => $query->whereHas(
                        'allocations',
                        fn ($allocations) => $allocations->where(
                            'client_service_id',
                            $clientServiceId
                        )
                    )
                )
                ->get();

        $observations =
            collect();

        foreach ($sets as $set) {
            /*
             * False means valid historical audit history whose
             * source state is no longer current, or whose invoice
             * status is not admissible for canonical billing.
             *
             * Structural corruption throws rather than silently
             * producing financial truth.
             */
            if (
                ! $this->allocationSetIsCurrent(
                    $set
                )
            ) {
                continue;
            }

            $item =
                $set->invoiceItem;

            $invoice =
                $item->invoice;

            $quantity =
                (float) $item->quantity;

            if (abs($quantity) < 0.0001) {
                throw new LogicException(
                    sprintf(
                        'Allocation-backed billing evidence %s has zero source quantity and cannot be normalised safely.',
                        $item->id
                    )
                );
            }

            foreach (
                $set->allocations as $allocation
            ) {
                $service =
                    $allocation->service;

                /*
                 * Soft-deleted canonical services remain preserved
                 * by audit history but do not contribute observed
                 * canonical billing.
                 */
                if ($service->trashed()) {
                    continue;
                }

                if (
                    $clientServiceId !== null
                    && (string) $service->id
                        !== $clientServiceId
                ) {
                    continue;
                }

                $allocatedNet =
                    ((int) $allocation
                        ->allocated_net_pence)
                    / 100;

                /*
                 * Preserve source quantity so existing catch-up
                 * cadence logic continues to work.
                 *
                 * Example:
                 * source qty 3, allocated £150
                 * => service-level unit £50, net £150, qty 3.
                 */
                $allocatedUnitPrice =
                    $allocatedNet
                    / $quantity;

                $observations->push(
                    new CanonicalBillingObservation(
                        invoice_item_id: (string) $item->id,

                        quantity: $quantity,

                        unit_price: $allocatedUnitPrice,

                        net_amount: $allocatedNet,

                        created_at: $this->nullableString(
                            $item->created_at
                        ),

                        invoice_date: $this->nullableString(
                            $invoice->invoice_date
                        ),

                        client_service_id: (string) $service->id,

                        client_id: (string) $service->client_id,

                        service_name: (string) $service->name,

                        service_status: (string) $service->status,

                        client_name: (string) $set->client->name,

                        attribution_source: 'allocation',

                        allocation_set_id: (string) $set->id,

                        allocation_id: (string) $allocation->id,
                    )
                );
            }
        }

        return $observations
            ->values();
    }

    /**
     * Validate a persisted allocation before allowing it to become
     * canonical observed billing evidence.
     *
     * Returns false only for legitimate stale/inadmissible audit
     * history. Invalid ledger structure raises an integrity error.
     */
    private function allocationSetIsCurrent(
        CommercialEvidenceAllocationSet $set
    ): bool {
        $review =
            $set->review;

        $item =
            $set->invoiceItem;

        $client =
            $set->client;

        if (
            $review === null
            || $item === null
            || $client === null
            || $item->invoice === null
        ) {
            throw new LogicException(
                sprintf(
                    'Commercial allocation set %s is missing required audit relationships.',
                    $set->id
                )
            );
        }

        if (
            $review->terminal_marker
                !== 'terminal'
            || ! in_array(
                $review->decision,
                [
                    'bundled_service',
                    'requires_allocation',
                ],
                true
            )
        ) {
            throw new LogicException(
                sprintf(
                    'Commercial allocation set %s does not reference a valid terminal structural review.',
                    $set->id
                )
            );
        }

        $expectedKind =
            $review->decision
                === 'bundled_service'
                    ? 'bundle'
                    : 'split';

        if (
            $set->allocation_kind
            !== $expectedKind
        ) {
            throw new LogicException(
                sprintf(
                    'Commercial allocation set %s conflicts with its structural review decision.',
                    $set->id
                )
            );
        }

        if (
            (string) $review
                ->accounting_invoice_item_id
                !== (string) $set
                    ->accounting_invoice_item_id
            || (string) $review->client_id
                !== (string) $set->client_id
            || (string) $review
                ->evidence_fingerprint
                !== (string) $set
                    ->evidence_fingerprint
        ) {
            throw new LogicException(
                sprintf(
                    'Commercial allocation set %s does not match its reviewed source identity.',
                    $set->id
                )
            );
        }

        if (
            (string) $item->invoice->client_id
            !== (string) $set->client_id
        ) {
            throw new LogicException(
                sprintf(
                    'Commercial allocation set %s does not belong to its source invoice client.',
                    $set->id
                )
            );
        }

        /*
         * Source changes intentionally invalidate the prior
         * allocation as current truth without deleting audit history.
         */
        $currentFingerprint =
            $this->evidenceFingerprint
                ->forInvoiceItem(
                    $item
                );

        if (
            $currentFingerprint
            !== $set->evidence_fingerprint
        ) {
            return false;
        }

        if (
            ! $this->statusPolicy->admits(
                $item->invoice->status
            )
        ) {
            return false;
        }

        /*
         * A current allocation must never coexist with direct
         * canonical service attribution on its source item.
         */
        if (
            $item->client_service_id
            !== null
        ) {
            throw new LogicException(
                sprintf(
                    'Commercial allocation source item %s is also directly attributed to a canonical service.',
                    $item->id
                )
            );
        }

        $sourceNetPence =
            $this->decimalToPence(
                (string) $item->net_amount
            );

        if (
            (int) $set->source_net_pence
            !== $sourceNetPence
        ) {
            throw new LogicException(
                sprintf(
                    'Commercial allocation set %s no longer conserves its recorded source value.',
                    $set->id
                )
            );
        }

        $allocations =
            $set->allocations;

        if ($allocations->isEmpty()) {
            throw new LogicException(
                sprintf(
                    'Commercial allocation set %s has no allocation lines.',
                    $set->id
                )
            );
        }

        if (
            $set->allocation_kind
                === 'bundle'
            && $allocations->count()
                !== 1
        ) {
            throw new LogicException(
                sprintf(
                    'Bundle allocation set %s must contain exactly one target.',
                    $set->id
                )
            );
        }

        if (
            $set->allocation_kind
                === 'split'
            && $allocations->count()
                < 2
        ) {
            throw new LogicException(
                sprintf(
                    'Split allocation set %s must contain at least two targets.',
                    $set->id
                )
            );
        }

        $allocatedTotal =
            0;

        foreach (
            $allocations as $allocation
        ) {
            $service =
                $allocation->service;

            if ($service === null) {
                throw new LogicException(
                    sprintf(
                        'Commercial allocation line %s has no canonical service target.',
                        $allocation->id
                    )
                );
            }

            if (
                (string) $service->client_id
                !== (string) $set->client_id
            ) {
                throw new LogicException(
                    sprintf(
                        'Commercial allocation line %s targets another client.',
                        $allocation->id
                    )
                );
            }

            if (
                ! in_array(
                    $service->status,
                    [
                        'active',
                        'historical',
                    ],
                    true
                )
            ) {
                throw new LogicException(
                    sprintf(
                        'Commercial allocation line %s targets a service with unsupported canonical status.',
                        $allocation->id
                    )
                );
            }

            $pence =
                (int) $allocation
                    ->allocated_net_pence;

            if (
                $pence === 0
                || (
                    $sourceNetPence > 0
                    && $pence < 0
                )
                || (
                    $sourceNetPence < 0
                    && $pence > 0
                )
            ) {
                throw new LogicException(
                    sprintf(
                        'Commercial allocation line %s has an invalid signed amount.',
                        $allocation->id
                    )
                );
            }

            $allocatedTotal +=
                $pence;
        }

        if (
            $allocatedTotal
            !== $sourceNetPence
        ) {
            throw new LogicException(
                sprintf(
                    'Commercial allocation set %s does not conserve source value: source %d pence, allocated %d pence.',
                    $set->id,
                    $sourceNetPence,
                    $allocatedTotal
                )
            );
        }

        return true;
    }

    private function decimalToPence(
        string $value
    ): int {
        $value =
            trim(
                $value
            );

        if (
            ! preg_match(
                '/^(-?)(\d+)(?:\.(\d{1,2}))?$/',
                $value,
                $matches
            )
        ) {
            throw new LogicException(
                sprintf(
                    'Commercial billing source value "%s" cannot be represented exactly in pence.',
                    $value
                )
            );
        }

        $major =
            (int) $matches[2];

        $minor =
            (int) str_pad(
                $matches[3] ?? '',
                2,
                '0'
            );

        $pence =
            ($major * 100)
            + $minor;

        return ($matches[1] ?? '')
            === '-'
                ? -$pence
                : $pence;
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
