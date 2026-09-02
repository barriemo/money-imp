<?php

namespace App\Domains\CommercialTruth\Services;

use App\Models\AccountingInvoiceItem;
use App\Models\ClientService;
use App\Models\CommercialEvidenceAllocationSet;
use App\Models\CompositeCommercialReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompositeCommercialAllocationService
{
    public function __construct(
        private readonly CompositeCommercialEvidenceFingerprint $evidenceFingerprint,
    ) {}

    /**
     * @param array<int, array{
     *     client_service_id: string,
     *     allocated_net_pence: int
     * }> $allocations
     */
    public function allocate(
        string $compositeReviewId,
        array $allocations,
        int $allocatedBy,
        ?string $reason = null
    ): CommercialEvidenceAllocationSet {
        return DB::transaction(
            function () use (
                $compositeReviewId,
                $allocations,
                $allocatedBy,
                $reason
            ): CommercialEvidenceAllocationSet {
                $review =
                    CompositeCommercialReview::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $compositeReviewId
                        );

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
                    throw ValidationException::withMessages([
                        'review' => 'A terminal bundle/allocation structural review is required before monetary allocation.',
                    ]);
                }

                $item =
                    AccountingInvoiceItem::query()
                        ->with('invoice')
                        ->lockForUpdate()
                        ->findOrFail(
                            $review
                                ->accounting_invoice_item_id
                        );

                if (
                    $item->invoice === null
                    || $item->invoice->client_id
                        !== $review->client_id
                ) {
                    throw ValidationException::withMessages([
                        'source' => 'The reviewed source evidence no longer belongs to the reviewed client.',
                    ]);
                }

                /*
                 * Composite allocation never mutates or competes
                 * with direct one-service attribution.
                 */
                if (
                    $item->client_service_id
                    !== null
                ) {
                    throw ValidationException::withMessages([
                        'source' => 'The source invoice item is already directly attributed to canonical service truth.',
                    ]);
                }

                /*
                 * The Stage 1 human decision applies only to the
                 * exact source evidence state that was reviewed.
                 */
                $currentFingerprint =
                    $this->evidenceFingerprint
                        ->forInvoiceItem(
                            $item
                        );

                if (
                    $currentFingerprint
                    !== $review->evidence_fingerprint
                ) {
                    throw ValidationException::withMessages([
                        'source' => 'The source evidence changed after structural review and must be reviewed again.',
                    ]);
                }

                $alreadyAllocated =
                    CommercialEvidenceAllocationSet::query()
                        ->where(
                            'composite_commercial_review_id',
                            $review->id
                        )
                        ->orWhere(
                            function ($query) use (
                                $item,
                                $currentFingerprint
                            ): void {
                                $query
                                    ->where(
                                        'accounting_invoice_item_id',
                                        $item->id
                                    )
                                    ->where(
                                        'evidence_fingerprint',
                                        $currentFingerprint
                                    );
                            }
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($alreadyAllocated) {
                    throw ValidationException::withMessages([
                        'allocation' => 'This exact reviewed source evidence already has an approved allocation set.',
                    ]);
                }

                $normalised =
                    $this->normaliseAllocations(
                        $allocations
                    );

                $serviceIds =
                    array_column(
                        $normalised,
                        'client_service_id'
                    );

                $services =
                    ClientService::query()
                        ->whereIn(
                            'id',
                            $serviceIds
                        )
                        ->lockForUpdate()
                        ->get()
                        ->keyBy(
                            fn (
                                ClientService $service
                            ) => (string) $service->id
                        );

                if (
                    $services->count()
                    !== count($serviceIds)
                ) {
                    throw ValidationException::withMessages([
                        'allocation' => 'Every allocation target must be an existing canonical client service.',
                    ]);
                }

                foreach ($serviceIds as $serviceId) {
                    /** @var ClientService $service */
                    $service =
                        $services->get(
                            $serviceId
                        );

                    if (
                        $service->client_id
                        !== $review->client_id
                    ) {
                        throw ValidationException::withMessages([
                            'allocation' => 'Every allocation target must belong to the same client as the source evidence.',
                        ]);
                    }

                    /*
                     * Status remains a separate human assertion.
                     *
                     * Historical services may legitimately receive
                     * historical evidence; only active services can
                     * later contribute to current observed billing.
                     */
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
                        throw ValidationException::withMessages([
                            'allocation' => 'Allocation targets must be active or historical canonical services.',
                        ]);
                    }
                }

                $sourceNetPence =
                    $this->decimalToPence(
                        (string) $item->net_amount
                    );

                if ($sourceNetPence === 0) {
                    throw ValidationException::withMessages([
                        'allocation' => 'Zero-value source evidence cannot be commercially allocated.',
                    ]);
                }

                $this->assertShapeAndConservation(
                    reviewDecision: $review->decision,
                    allocations: $normalised,
                    sourceNetPence: $sourceNetPence
                );

                $kind =
                    $review->decision
                        === 'bundled_service'
                            ? 'bundle'
                            : 'split';

                $targetSnapshot =
                    collect($normalised)
                        ->map(
                            function (
                                array $allocation
                            ) use (
                                $services
                            ): array {
                                /** @var ClientService $service */
                                $service =
                                    $services->get(
                                        $allocation[
                                            'client_service_id'
                                        ]
                                    );

                                return [
                                    'client_service_id' => (string) $service->id,

                                    'service_name' => $service->name,

                                    'service_status' => $service->status,

                                    'allocated_net_pence' => $allocation[
                                            'allocated_net_pence'
                                        ],
                                ];
                            }
                        )
                        ->values()
                        ->all();

                $set =
                    CommercialEvidenceAllocationSet::create([
                        'composite_commercial_review_id' => $review->id,

                        'accounting_invoice_item_id' => $item->id,

                        'client_id' => $review->client_id,

                        'evidence_fingerprint' => $currentFingerprint,

                        'allocation_kind' => $kind,

                        'source_net_pence' => $sourceNetPence,

                        'allocated_by' => $allocatedBy,

                        'allocated_at' => now(),

                        'reason' => $this->cleanReason(
                            $reason
                        ),

                        'allocation_snapshot' => [
                        'composite_review_id' => (string) $review->id,

                        'structural_decision' => $review->decision,

                        'accounting_invoice_item_id' => (string) $item->id,

                        'client_id' => (string) $review->client_id,

                        'evidence_fingerprint' => $currentFingerprint,

                        'source_net_pence' => $sourceNetPence,

                        'invoice_id' => (string) $item
                            ->accounting_invoice_id,

                        'invoice_number' => $item->invoice
                            ->invoice_number,

                        'invoice_date' => $item->invoice
                            ->invoice_date
                            ?->toDateString(),

                        'invoice_status' => $item->invoice
                            ->status,

                        'description' => $item->description,

                        'targets' => $targetSnapshot,
                        ],
                    ]);

                foreach ($normalised as $allocation) {
                    $set->allocations()->create([
                        'client_service_id' => $allocation[
                                'client_service_id'
                            ],

                        'allocated_net_pence' => $allocation[
                                'allocated_net_pence'
                            ],
                    ]);
                }

                /*
                 * Deliberately do NOT update:
                 *
                 * accounting_invoice_items.client_service_id
                 * client_services
                 * billing rules
                 * canonical observed billing
                 *
                 * Stage 2A records the human monetary
                 * interpretation only.
                 */
                return $set->load([
                    'review',
                    'invoiceItem.invoice',
                    'allocations.service',
                ]);
            }
        );
    }

    /**
     * @param  array<int, mixed>  $allocations
     * @return array<int, array{
     *     client_service_id: string,
     *     allocated_net_pence: int
     * }>
     */
    private function normaliseAllocations(
        array $allocations
    ): array {
        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocation' => 'At least one target allocation is required.',
            ]);
        }

        $normalised = [];

        foreach ($allocations as $allocation) {
            if (
                ! is_array($allocation)
                || ! isset(
                    $allocation[
                        'client_service_id'
                    ]
                )
                || ! is_string(
                    $allocation[
                        'client_service_id'
                    ]
                )
                || trim(
                    $allocation[
                        'client_service_id'
                    ]
                ) === ''
                || ! array_key_exists(
                    'allocated_net_pence',
                    $allocation
                )
                || ! is_int(
                    $allocation[
                        'allocated_net_pence'
                    ]
                )
            ) {
                throw ValidationException::withMessages([
                    'allocation' => 'Each allocation requires a canonical service id and an integer pence amount.',
                ]);
            }

            $normalised[] = [
                'client_service_id' => trim(
                    $allocation[
                        'client_service_id'
                    ]
                ),

                'allocated_net_pence' => $allocation[
                        'allocated_net_pence'
                    ],
            ];
        }

        usort(
            $normalised,
            fn (
                array $left,
                array $right
            ): int => strcmp(
                $left['client_service_id'],
                $right['client_service_id']
            )
        );

        $serviceIds =
            array_column(
                $normalised,
                'client_service_id'
            );

        if (
            count(array_unique($serviceIds))
            !== count($serviceIds)
        ) {
            throw ValidationException::withMessages([
                'allocation' => 'A canonical service may appear only once in one allocation set.',
            ]);
        }

        return $normalised;
    }

    /**
     * @param array<int, array{
     *     client_service_id: string,
     *     allocated_net_pence: int
     * }> $allocations
     */
    private function assertShapeAndConservation(
        string $reviewDecision,
        array $allocations,
        int $sourceNetPence
    ): void {
        if (
            $reviewDecision === 'bundled_service'
            && count($allocations) !== 1
        ) {
            throw ValidationException::withMessages([
                'allocation' => 'Bundled evidence must allocate 100% of source value to exactly one canonical service.',
            ]);
        }

        if (
            $reviewDecision === 'requires_allocation'
            && count($allocations) < 2
        ) {
            throw ValidationException::withMessages([
                'allocation' => 'Evidence reviewed as requiring allocation must be split across at least two canonical services.',
            ]);
        }

        foreach ($allocations as $allocation) {
            $pence =
                $allocation[
                    'allocated_net_pence'
                ];

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
                throw ValidationException::withMessages([
                    'allocation' => 'Every allocation line must be non-zero and have the same sign as the source evidence.',
                ]);
            }
        }

        $allocatedTotal =
            array_sum(
                array_column(
                    $allocations,
                    'allocated_net_pence'
                )
            );

        if (
            $allocatedTotal
            !== $sourceNetPence
        ) {
            throw ValidationException::withMessages([
                'allocation' => sprintf(
                    'Allocated value must conserve source value exactly: source %d pence, allocated %d pence.',
                    $sourceNetPence,
                    $allocatedTotal
                ),
            ]);
        }
    }

    private function decimalToPence(
        string $value
    ): int {
        $value =
            trim($value);

        if (
            ! preg_match(
                '/^(-?)(\d+)(?:\.(\d{1,2}))?$/',
                $value,
                $matches
            )
        ) {
            throw ValidationException::withMessages([
                'source' => 'Source net amount cannot be represented exactly in pence.',
            ]);
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

        return ($matches[1] ?? '') === '-'
            ? -$pence
            : $pence;
    }

    private function cleanReason(
        ?string $reason
    ): ?string {
        if ($reason === null) {
            return null;
        }

        $reason =
            trim($reason);

        return $reason !== ''
            ? $reason
            : null;
    }
}
