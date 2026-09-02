<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Models\AccountingInvoiceItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CompositeCommercialEvidenceReviewQueueService
{
    public function __construct(
        private readonly ClientServiceCandidateAssessmentService $assessments,
    ) {}

    /**
     * Composite evidence is deliberately read-only here.
     *
     * It is source evidence spanning multiple material
     * commercial activities and must not be promoted wholesale
     * into a canonical service or allocated without human
     * commercial interpretation.
     *
     * Human review must establish whether the evidence represents
     * one bundled service or genuinely requires monetary
     * decomposition.
     *
     * @return Collection<int, ClientServiceCandidateAssessment>
     */
    public function ready(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??= CarbonImmutable::today();

        return $this->assessments
            ->all(
                $asOf
            )
            ->filter(
                fn (
                    ClientServiceCandidateAssessment $assessment
                ) => $assessment
                    ->candidate
                    ->isCompositeCandidate()
                    && $assessment
                        ->promotionReadiness
                        === 'needs_commercial_review'
            )
            ->filter(
                fn (
                    ClientServiceCandidateAssessment $assessment
                ) => ! $this->hasCanonicalAttribution(
                    $assessment
                )
            )
            ->values();
    }

    private function hasCanonicalAttribution(
        ClientServiceCandidateAssessment $assessment
    ): bool {
        $invoiceItemIds =
            $assessment
                ->candidate
                ->invoiceItemIds;

        if ($invoiceItemIds === []) {
            return false;
        }

        return AccountingInvoiceItem::query()
            ->whereIn(
                'id',
                $invoiceItemIds
            )
            ->whereNotNull(
                'client_service_id'
            )
            ->exists();
    }
}
