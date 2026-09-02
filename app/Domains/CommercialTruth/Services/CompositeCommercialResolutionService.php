<?php

namespace App\Domains\CommercialTruth\Services;

use App\Models\AccountingInvoiceItem;
use App\Models\ClientService;
use App\Models\CommercialEvidenceAllocationSet;
use App\Models\CompositeCommercialResolutionTarget;
use App\Models\CompositeCommercialReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompositeCommercialResolutionService
{
    public function __construct(
        private readonly CompositeCommercialEvidenceFingerprint $evidenceFingerprint,
        private readonly CompositeCommercialAllocationService $allocator,
    ) {}

    /**
     * Complete a terminal composite structural review by resolving
     * its canonical target service(s) and monetary allocation in one
     * transaction.
     *
     * Each target must provide exactly one of:
     *
     * client_service_id
     * service_name
     *
     * service_status is the explicit resulting human assertion.
     *
     * Existing active -> historical demotion is deliberately not
     * supported here. Existing historical -> active reactivation is
     * explicit and audited.
     *
     * @param array<int, array{
     *     client_service_id?: string|null,
     *     service_name?: string|null,
     *     service_status: string,
     *     allocated_net_pence: int
     * }> $targets
     */
    public function resolve(
        string $compositeReviewId,
        array $targets,
        int $resolvedBy,
        ?string $reason = null
    ): CommercialEvidenceAllocationSet {
        $normalised =
            $this->normaliseTargets(
                $targets
            );

        $reason =
            $this->cleanReason(
                $reason
            );

        return DB::transaction(
            function () use (
                $compositeReviewId,
                $normalised,
                $resolvedBy,
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
                        'review' => 'A terminal bundle/allocation structural review is required before composite resolution.',
                    ]);
                }

                $alreadyAllocated =
                    CommercialEvidenceAllocationSet::query()
                        ->where(
                            'composite_commercial_review_id',
                            $review->id
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($alreadyAllocated) {
                    throw ValidationException::withMessages([
                        'review' => 'This composite review is already commercially resolved.',
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
                    || (string) $item->invoice->client_id
                        !== (string) $review->client_id
                ) {
                    throw ValidationException::withMessages([
                        'source' => 'The reviewed source evidence no longer belongs to the reviewed client.',
                    ]);
                }

                if (
                    $item->client_service_id
                    !== null
                ) {
                    throw ValidationException::withMessages([
                        'source' => 'Composite source evidence is already directly attributed to canonical service truth.',
                    ]);
                }

                $currentFingerprint =
                    $this->evidenceFingerprint
                        ->forInvoiceItem(
                            $item
                        );

                if (
                    ! hash_equals(
                        (string) $review
                            ->evidence_fingerprint,
                        $currentFingerprint
                    )
                ) {
                    throw ValidationException::withMessages([
                        'source' => 'The source evidence changed after structural review and must be reviewed again.',
                    ]);
                }

                $resolvedAt =
                    now();

                $resolvedTargets =
                    [];

                foreach (
                    $normalised as $target
                ) {
                    $resolvedTargets[] =
                        $this->resolveTarget(
                            review: $review,
                            target: $target,
                            resolvedBy: $resolvedBy,
                            resolvedAt: $resolvedAt,
                            reason: $reason
                        );
                }

                /*
                 * The existing allocation service remains the single
                 * monetary-conservation authority.
                 *
                 * Any failure here rolls back service creation,
                 * reactivation and target-resolution work above.
                 */
                $set =
                    $this->allocator
                        ->allocate(
                            compositeReviewId: (string) $review->id,

                            allocations: collect(
                                $resolvedTargets
                            )
                                ->map(
                                    fn (
                                        array $target
                                    ) => [
                                        'client_service_id' => (string) $target[
                                                'service'
                                            ]->id,

                                        'allocated_net_pence' => $target[
                                                'allocated_net_pence'
                                            ],
                                    ]
                                )
                                ->all(),

                            allocatedBy: $resolvedBy,

                            reason: $reason
                        );

                $allocationLines =
                    $set->allocations
                        ->keyBy(
                            fn ($allocation) => (string) $allocation
                                ->client_service_id
                        );

                foreach (
                    $resolvedTargets as $target
                ) {
                    /** @var ClientService $service */
                    $service =
                        $target['service'];

                    $allocation =
                        $allocationLines->get(
                            (string) $service->id
                        );

                    if ($allocation === null) {
                        throw ValidationException::withMessages([
                            'resolution' => 'The approved allocation did not produce the expected canonical target line.',
                        ]);
                    }

                    CompositeCommercialResolutionTarget::create([
                        'composite_commercial_review_id' => $review->id,

                        'allocation_set_id' => $set->id,

                        'commercial_evidence_allocation_id' => $allocation->id,

                        'client_id' => $review->client_id,

                        'client_service_id' => $service->id,

                        'target_action' => $target[
                                'target_action'
                            ],

                        'previous_service_status' => $target[
                                'previous_service_status'
                            ],

                        'resulting_service_status' => $target[
                                'resulting_service_status'
                            ],

                        'allocated_net_pence' => $target[
                                'allocated_net_pence'
                            ],

                        'resolved_by' => $resolvedBy,

                        'resolved_at' => $resolvedAt,

                        'reason' => $reason,

                        'resolution_snapshot' => [
                            'composite_review_id' => (string) $review->id,

                            'structural_decision' => $review->decision,

                            'accounting_invoice_item_id' => (string) $item->id,

                            'client_id' => (string) $review->client_id,

                            'evidence_fingerprint' => $currentFingerprint,

                            'allocation_set_id' => (string) $set->id,

                            'commercial_evidence_allocation_id' => (string) $allocation->id,

                            'client_service_id' => (string) $service->id,

                            'service_name' => $service->name,

                            'target_action' => $target[
                                    'target_action'
                                ],

                            'previous_service_status' => $target[
                                    'previous_service_status'
                                ],

                            'resulting_service_status' => $target[
                                    'resulting_service_status'
                                ],

                            'allocated_net_pence' => $target[
                                    'allocated_net_pence'
                                ],
                        ],
                    ]);
                }

                /*
                 * Source accounting evidence deliberately remains
                 * untouched. Canonical billing consumes the approved
                 * allocation ledger through Stage 2B.
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
     * @param array{
     *     client_service_id: ?string,
     *     service_name: ?string,
     *     service_status: string,
     *     allocated_net_pence: int
     * } $target
     * @return array{
     *     service: ClientService,
     *     target_action: string,
     *     previous_service_status: ?string,
     *     resulting_service_status: string,
     *     allocated_net_pence: int
     * }
     */
    private function resolveTarget(
        CompositeCommercialReview $review,
        array $target,
        int $resolvedBy,
        mixed $resolvedAt,
        ?string $reason
    ): array {
        if (
            $target['client_service_id']
            !== null
        ) {
            $service =
                ClientService::query()
                    ->lockForUpdate()
                    ->find(
                        $target[
                            'client_service_id'
                        ]
                    );

            if ($service === null) {
                throw ValidationException::withMessages([
                    'target' => 'The selected canonical service does not exist.',
                ]);
            }

            if (
                (string) $service->client_id
                !== (string) $review->client_id
            ) {
                throw ValidationException::withMessages([
                    'target' => 'The selected canonical service belongs to another client.',
                ]);
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
                throw ValidationException::withMessages([
                    'target' => 'Existing composite targets must already be active or historical canonical services.',
                ]);
            }

            $previousStatus =
                (string) $service->status;

            if (
                $previousStatus === 'active'
                && $target[
                    'service_status'
                ] === 'historical'
            ) {
                throw ValidationException::withMessages([
                    'target' => 'Composite resolution may not silently demote an active canonical service to historical.',
                ]);
            }

            if (
                $previousStatus === 'historical'
                && $target[
                    'service_status'
                ] === 'active'
            ) {
                /*
                 * Explicit human lifecycle assertion.
                 *
                 * This does not alter historic invoice evidence.
                 */
                $service->update([
                    'status' => 'active',
                ]);

                $action =
                    'reactivated';
            } else {
                $action =
                    'existing';
            }

            return [
                'service' => $service->fresh(),

                'target_action' => $action,

                'previous_service_status' => $previousStatus,

                'resulting_service_status' => $target[
                        'service_status'
                    ],

                'allocated_net_pence' => $target[
                        'allocated_net_pence'
                    ],
            ];
        }

        $service =
            ClientService::create([
                'client_id' => $review->client_id,

                'name' => $target[
                        'service_name'
                    ],

                'type' => 'service',

                'status' => $target[
                        'service_status'
                    ],

                'metadata' => [
                    'source' => 'human_composite_resolution',

                    'canonical_status_basis' => 'human_composite_resolution',

                    'composite_review_id' => (string) $review->id,

                    'evidence_fingerprint' => (string) $review
                        ->evidence_fingerprint,

                    'resolved_by' => $resolvedBy,

                    'resolved_at' => $resolvedAt
                        ->toIso8601String(),

                    'reason' => $reason,
                ],
            ]);

        return [
            'service' => $service,

            'target_action' => 'created',

            'previous_service_status' => null,

            'resulting_service_status' => $target[
                    'service_status'
                ],

            'allocated_net_pence' => $target[
                    'allocated_net_pence'
                ],
        ];
    }

    /**
     * @param  array<int, mixed>  $targets
     * @return array<int, array{
     *     client_service_id: ?string,
     *     service_name: ?string,
     *     service_status: string,
     *     allocated_net_pence: int
     * }>
     */
    private function normaliseTargets(
        array $targets
    ): array {
        if ($targets === []) {
            throw ValidationException::withMessages([
                'target' => 'At least one canonical resolution target is required.',
            ]);
        }

        $normalised =
            [];

        $identities =
            [];

        foreach ($targets as $target) {
            if (! is_array($target)) {
                throw ValidationException::withMessages([
                    'target' => 'Every composite resolution target must be an array.',
                ]);
            }

            $serviceId =
                isset(
                    $target[
                        'client_service_id'
                    ]
                )
                && is_string(
                    $target[
                        'client_service_id'
                    ]
                )
                    ? trim(
                        $target[
                            'client_service_id'
                        ]
                    )
                    : null;

            $serviceName =
                isset(
                    $target[
                        'service_name'
                    ]
                )
                && is_string(
                    $target[
                        'service_name'
                    ]
                )
                    ? trim(
                        $target[
                            'service_name'
                        ]
                    )
                    : null;

            $serviceId =
                $serviceId !== ''
                    ? $serviceId
                    : null;

            $serviceName =
                $serviceName !== ''
                    ? $serviceName
                    : null;

            /*
             * Exactly one targeting mechanism.
             */
            if (
                ($serviceId === null)
                === ($serviceName === null)
            ) {
                throw ValidationException::withMessages([
                    'target' => 'Each resolution target requires exactly one existing service id or new service name.',
                ]);
            }

            $status =
                $target[
                    'service_status'
                ]
                    ?? null;

            if (
                ! is_string($status)
                || ! in_array(
                    $status,
                    [
                        'active',
                        'historical',
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'target' => 'Every resolution target requires an explicit active or historical service status.',
                ]);
            }

            if (
                ! array_key_exists(
                    'allocated_net_pence',
                    $target
                )
                || ! is_int(
                    $target[
                        'allocated_net_pence'
                    ]
                )
            ) {
                throw ValidationException::withMessages([
                    'target' => 'Every resolution target requires an integer pence allocation.',
                ]);
            }

            $identity =
                $serviceId !== null
                    ? 'existing:'
                        .$serviceId
                    : 'new:'
                        .strtolower(
                            $serviceName
                        );

            if (
                isset(
                    $identities[
                        $identity
                    ]
                )
            ) {
                throw ValidationException::withMessages([
                    'target' => 'The same canonical resolution target may appear only once.',
                ]);
            }

            $identities[
                $identity
            ] = true;

            $normalised[] = [
                'client_service_id' => $serviceId,

                'service_name' => $serviceName,

                'service_status' => $status,

                'allocated_net_pence' => $target[
                        'allocated_net_pence'
                    ],
            ];
        }

        return $normalised;
    }

    private function cleanReason(
        ?string $reason
    ): ?string {
        if ($reason === null) {
            return null;
        }

        $reason =
            trim(
                $reason
            );

        return $reason !== ''
            ? $reason
            : null;
    }
}
