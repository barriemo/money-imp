<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Models\AccountingInvoiceItem;
use App\Models\ClientServiceReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientServiceReconciliationService
{
    public function __construct(
        private readonly ClientServiceCandidateAssessmentService $assessments,
        private readonly ClientServiceCandidateEvidenceFingerprint $evidenceFingerprint,
    ) {}

    public function reject(
        string $clientId,
        string $candidateFingerprint,
        int $reviewedBy,
        ?string $reason = null,
        ?CarbonImmutable $asOf = null
    ): ClientServiceReconciliation {
        return $this->review(
            clientId: $clientId,
            candidateFingerprint: $candidateFingerprint,
            decision: 'rejected',
            reviewedBy: $reviewedBy,
            reason: $reason,
            asOf: $asOf
        );
    }

    public function defer(
        string $clientId,
        string $candidateFingerprint,
        int $reviewedBy,
        ?string $reason = null,
        ?CarbonImmutable $asOf = null
    ): ClientServiceReconciliation {
        return $this->review(
            clientId: $clientId,
            candidateFingerprint: $candidateFingerprint,
            decision: 'deferred',
            reviewedBy: $reviewedBy,
            reason: $reason,
            asOf: $asOf
        );
    }

    private function review(
        string $clientId,
        string $candidateFingerprint,
        string $decision,
        int $reviewedBy,
        ?string $reason,
        ?CarbonImmutable $asOf
    ): ClientServiceReconciliation {
        $asOf ??=
            CarbonImmutable::today();

        return DB::transaction(
            function () use (
                $clientId,
                $candidateFingerprint,
                $decision,
                $reviewedBy,
                $reason,
                $asOf
            ): ClientServiceReconciliation {
                $assessment =
                    $this->resolveAssessment(
                        clientId: $clientId,
                        candidateFingerprint: $candidateFingerprint,
                        asOf: $asOf
                    );

                $candidate =
                    $assessment->candidate;

                $invoiceItemIds =
                    $candidate->invoiceItemIds;

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
                        'candidate' => 'The commercial evidence set changed before review and must be reassessed.',
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
                        'candidate' => 'The commercial evidence set no longer matches the reviewed candidate.',
                    ]);
                }

                foreach ($items as $item) {
                    if (
                        $item->invoice === null
                        || $item->invoice->client_id
                            !== $clientId
                    ) {
                        throw ValidationException::withMessages([
                            'candidate' => 'All reviewed invoice evidence must belong to the same client.',
                        ]);
                    }

                    if (
                        $item->client_service_id
                        !== null
                    ) {
                        throw ValidationException::withMessages([
                            'candidate' => 'At least one reviewed invoice item is already attributed to canonical service truth.',
                        ]);
                    }
                }

                /*
                 * Re-resolve after locking the evidence rows.
                 *
                 * We do not silently act on a candidate that has
                 * become stale or ceased to be ready for review.
                 */
                $assessment =
                    $this->resolveAssessment(
                        clientId: $clientId,
                        candidateFingerprint: $candidateFingerprint,
                        asOf: $asOf
                    );

                $candidate =
                    $assessment->candidate;

                $evidenceFingerprint =
                    $this->evidenceFingerprint
                        ->forCandidate(
                            $candidate
                        );

                $snapshot = [
                    'as_of_date' => $assessment->asOfDate,

                    'client_id' => $candidate->clientId,

                    'client_name' => $candidate->clientName,

                    'service_type' => $candidate->serviceType,

                    'service_hint' => $candidate->serviceHint,

                    'candidate_fingerprint' => $candidate->fingerprint,

                    'commercial_treatment' => $candidate
                        ->commercialTreatment,

                    'evidence_count' => $candidate->evidenceCount,

                    'invoice_item_ids' => $invoiceItemIds,

                    'signed_observed_net' => $candidate
                        ->signedObservedNet,

                    'positive_observed_net' => $candidate
                        ->positiveObservedNet,

                    'negative_observed_net' => $candidate
                        ->negativeObservedNet,

                    'latest_observed_unit_price' => $candidate
                        ->latestObservedUnitPrice,

                    'first_observed_on' => $candidate
                        ->firstObservedOn,

                    'last_observed_on' => $candidate
                        ->lastObservedOn,

                    'cadence' => $candidate->cadence,

                    'monthly_equivalent' => $candidate
                        ->monthlyEquivalent,

                    'classification_confidence' => $candidate
                        ->classificationConfidence,

                    'cadence_confidence' => $candidate
                        ->cadenceConfidence,

                    'freshness' => $assessment->freshness,

                    'recurring_evidence' => $assessment
                        ->recurringEvidence,

                    'current_monthly_equivalent' => $assessment
                        ->currentMonthlyEquivalent,

                    'promotion_readiness' => $assessment
                        ->promotionReadiness,

                    'assessment_reasons' => $assessment->reasons,
                ];

                return ClientServiceReconciliation::create([
                    'client_id' => $clientId,

                    'candidate_fingerprint' => $candidate
                        ->fingerprint,

                    'evidence_fingerprint' => $evidenceFingerprint,

                    'service_type' => $candidate
                        ->serviceType,

                    'service_hint' => $candidate
                        ->serviceHint,

                    'decision' => $decision,

                    'client_service_id' => null,

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

    private function resolveAssessment(
        string $clientId,
        string $candidateFingerprint,
        CarbonImmutable $asOf
    ): ClientServiceCandidateAssessment {
        $assessment =
            $this->assessments
                ->all($asOf)
                ->first(
                    fn (
                        ClientServiceCandidateAssessment $row
                    ) => $row->candidate
                        ->clientId
                            === $clientId
                        && $row->candidate
                            ->fingerprint
                            === $candidateFingerprint
                );

        if ($assessment === null) {
            throw ValidationException::withMessages([
                'candidate' => 'The commercial service candidate no longer exists in current evidence.',
            ]);
        }

        if (
            ! $assessment
                ->candidate
                ->isServiceCandidate()
        ) {
            throw ValidationException::withMessages([
                'candidate' => 'This evidence is not eligible for client service reconciliation.',
            ]);
        }

        if (
            $assessment
                ->promotionReadiness
            !== 'ready_for_review'
        ) {
            throw ValidationException::withMessages([
                'candidate' => 'This commercial service candidate is not currently ready for human reconciliation.',
            ]);
        }

        return $assessment;
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
