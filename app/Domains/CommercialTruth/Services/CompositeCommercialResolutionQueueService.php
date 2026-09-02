<?php

namespace App\Domains\CommercialTruth\Services;

use App\Models\CommercialEvidenceAllocationSet;
use App\Models\CompositeCommercialReview;
use Illuminate\Support\Collection;

final class CompositeCommercialResolutionQueueService
{
    public function __construct(
        private readonly CompositeCommercialEvidenceFingerprint $evidenceFingerprint,
    ) {}

    /**
     * Terminal structural reviews whose exact current source state
     * still needs monetary/canonical target completion.
     *
     * A changed source fingerprint intentionally leaves this queue:
     * the changed evidence reappears in the structural review queue
     * instead.
     *
     * @return Collection<int, CompositeCommercialReview>
     */
    public function ready(): Collection
    {
        return CompositeCommercialReview::query()
            ->with([
                'invoiceItem.invoice',
                'client',
            ])
            ->where(
                'terminal_marker',
                'terminal'
            )
            ->whereIn(
                'decision',
                [
                    'bundled_service',
                    'requires_allocation',
                ]
            )
            ->orderBy('reviewed_at')
            ->orderBy('id')
            ->get()
            ->filter(
                fn (
                    CompositeCommercialReview $review
                ) => $this->isCurrentPendingReview(
                    $review
                )
            )
            ->values();
    }

    private function isCurrentPendingReview(
        CompositeCommercialReview $review
    ): bool {
        if (
            CommercialEvidenceAllocationSet::query()
                ->where(
                    'composite_commercial_review_id',
                    $review->id
                )
                ->exists()
        ) {
            return false;
        }

        $item =
            $review->invoiceItem;

        if (
            $item === null
            || $item->invoice === null
        ) {
            return false;
        }

        if (
            (string) $item->invoice->client_id
            !== (string) $review->client_id
        ) {
            return false;
        }

        /*
         * Composite evidence never competes with direct canonical
         * attribution.
         */
        if (
            $item->client_service_id
            !== null
        ) {
            return false;
        }

        $currentFingerprint =
            $this->evidenceFingerprint
                ->forInvoiceItem(
                    $item
                );

        return hash_equals(
            (string) $review->evidence_fingerprint,
            $currentFingerprint
        );
    }
}
