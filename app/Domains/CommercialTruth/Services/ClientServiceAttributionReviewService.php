<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceAttributionCandidate;
use App\Models\AccountingInvoiceItem;
use App\Models\ClientService;
use App\Models\ClientServiceAttributionReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientServiceAttributionReviewService
{
    public function __construct(
        private readonly ClientServiceAttributionReviewQueueService $queue,
        private readonly ClientServiceAttributionEvidenceFingerprint $evidenceFingerprint,
    ) {}

    public function approve(
        string $clientId,
        string $candidateFingerprint,
        int $reviewedBy,
        ?string $reason = null
    ): ClientServiceAttributionReview {
        return $this->review(
            clientId: $clientId,
            candidateFingerprint: $candidateFingerprint,
            decision: 'approved',
            reviewedBy: $reviewedBy,
            reason: $reason
        );
    }

    public function reject(
        string $clientId,
        string $candidateFingerprint,
        int $reviewedBy,
        ?string $reason = null
    ): ClientServiceAttributionReview {
        return $this->review(
            clientId: $clientId,
            candidateFingerprint: $candidateFingerprint,
            decision: 'rejected',
            reviewedBy: $reviewedBy,
            reason: $reason
        );
    }

    private function review(
        string $clientId,
        string $candidateFingerprint,
        string $decision,
        int $reviewedBy,
        ?string $reason
    ): ClientServiceAttributionReview {
        return DB::transaction(
            function () use (
                $clientId,
                $candidateFingerprint,
                $decision,
                $reviewedBy,
                $reason
            ): ClientServiceAttributionReview {
                $candidate =
                    $this->resolveCandidate(
                        clientId: $clientId,
                        candidateFingerprint: $candidateFingerprint
                    );

                if (
                    $candidate
                        ->clientServiceId
                    === null
                ) {
                    throw ValidationException::withMessages([
                        'attribution' => 'This attribution candidate does not have a unique canonical service target.',
                    ]);
                }

                $invoiceItemIds =
                    $candidate
                        ->invoiceItemIds;

                sort(
                    $invoiceItemIds,
                    SORT_STRING
                );

                $items =
                    AccountingInvoiceItem::query()
                        ->with('invoice')
                        ->whereIn(
                            'id',
                            $invoiceItemIds
                        )
                        ->lockForUpdate()
                        ->get();

                if (
                    $items->count()
                    !== count(
                        $invoiceItemIds
                    )
                ) {
                    throw ValidationException::withMessages([
                        'attribution' => 'The attribution evidence changed before review and must be reassessed.',
                    ]);
                }

                $actualIds =
                    $items
                        ->pluck('id')
                        ->map(
                            fn ($id) => (string) $id
                        )
                        ->sort()
                        ->values()
                        ->all();

                if (
                    $actualIds
                    !== $invoiceItemIds
                ) {
                    throw ValidationException::withMessages([
                        'attribution' => 'The attribution evidence no longer matches the reviewed candidate.',
                    ]);
                }

                foreach ($items as $item) {
                    if (
                        $item->invoice === null
                        || $item->invoice
                            ->client_id
                            !== $clientId
                    ) {
                        throw ValidationException::withMessages([
                            'attribution' => 'All attribution evidence must belong to the same client.',
                        ]);
                    }

                    if (
                        $item
                            ->client_service_id
                        !== null
                    ) {
                        throw ValidationException::withMessages([
                            'attribution' => 'At least one reviewed invoice item is already attributed to canonical service truth.',
                        ]);
                    }
                }

                $service =
                    ClientService::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $candidate
                                ->clientServiceId
                        );

                if (
                    $service->client_id
                    !== $clientId
                ) {
                    throw ValidationException::withMessages([
                        'client_service' => 'The canonical service must belong to the same client as the reviewed invoice evidence.',
                    ]);
                }

                if (
                    $service->status
                    !== 'active'
                ) {
                    throw ValidationException::withMessages([
                        'client_service' => 'The canonical service is no longer active and cannot receive this attribution.',
                    ]);
                }

                /*
                 * Re-resolve after locking every write target.
                 *
                 * The human decision may only apply if the exact
                 * evidence still maps uniquely to the same active
                 * canonical service.
                 */
                $currentCandidate =
                    $this->resolveCandidate(
                        clientId: $clientId,
                        candidateFingerprint: $candidateFingerprint
                    );

                $currentIds =
                    $currentCandidate
                        ->invoiceItemIds;

                sort(
                    $currentIds,
                    SORT_STRING
                );

                if (
                    $currentIds
                    !== $invoiceItemIds
                ) {
                    throw ValidationException::withMessages([
                        'attribution' => 'The attribution evidence changed during review and must be reassessed.',
                    ]);
                }

                if (
                    $currentCandidate
                        ->clientServiceId
                    !== $service->id
                ) {
                    throw ValidationException::withMessages([
                        'attribution' => 'The canonical service mapping changed during review and must be reassessed.',
                    ]);
                }

                $evidenceFingerprint =
                    $this->evidenceFingerprint
                        ->forCandidate(
                            $currentCandidate
                        );

                $snapshot = [
                    'client_id' => $currentCandidate
                        ->clientId,

                    'client_name' => $currentCandidate
                        ->clientName,

                    'candidate_fingerprint' => $currentCandidate
                        ->candidateFingerprint,

                    'service_type' => $currentCandidate
                        ->serviceType,

                    'service_hint' => $currentCandidate
                        ->serviceHint,

                    'invoice_item_ids' => $invoiceItemIds,

                    'evidence_count' => $currentCandidate
                        ->evidenceCount,

                    'signed_observed_net' => $currentCandidate
                        ->signedObservedNet,

                    'first_observed_on' => $currentCandidate
                        ->firstObservedOn,

                    'last_observed_on' => $currentCandidate
                        ->lastObservedOn,

                    'match_status' => $currentCandidate
                        ->matchStatus,

                    'client_service_id' => $service->id,

                    'client_service_name' => $service->name,

                    'candidate_client_service_ids' => $currentCandidate
                        ->candidateClientServiceIds,

                    'supporting_reconciliation_ids' => $currentCandidate
                        ->supportingReconciliationIds,
                ];

                if ($decision === 'approved') {
                    foreach ($items as $item) {
                        $item->update([
                            'client_service_id' => $service->id,
                        ]);
                    }
                }

                return ClientServiceAttributionReview::create([
                    'client_id' => $clientId,

                    'client_service_id' => $service->id,

                    'candidate_fingerprint' => $currentCandidate
                        ->candidateFingerprint,

                    'evidence_fingerprint' => $evidenceFingerprint,

                    'decision' => $decision,

                    'reviewed_by' => $reviewedBy,

                    'reviewed_at' => now(),

                    'reason' => $this->cleanReason(
                        $reason
                    ),

                    'candidate_snapshot' => $snapshot,
                ]);
            }
        );
    }

    private function resolveCandidate(
        string $clientId,
        string $candidateFingerprint
    ): ClientServiceAttributionCandidate {
        $candidate =
            $this->queue
                ->ready()
                ->first(
                    fn (
                        ClientServiceAttributionCandidate $row
                    ) => $row->clientId
                            === $clientId
                        && $row
                            ->candidateFingerprint
                            === $candidateFingerprint
                );

        if ($candidate === null) {
            throw ValidationException::withMessages([
                'attribution' => 'The client service attribution candidate is no longer awaiting human review.',
            ]);
        }

        if (
            ! $candidate
                ->isReadyForReview()
        ) {
            throw ValidationException::withMessages([
                'attribution' => 'The client service attribution candidate no longer has a unique safe service match.',
            ]);
        }

        return $candidate;
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
