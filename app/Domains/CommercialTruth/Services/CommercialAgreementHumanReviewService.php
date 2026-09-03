<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\CommercialAgreementCoverageReviewCandidate;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommercialAgreementHumanReviewService
{
    public const ACTION_ESTABLISH_TERMS =
        'establish_terms';

    public const ACTION_CONFIRM_TERMS =
        'confirm_terms';

    public const ACTION_NO_CURRENT_CONTRACT =
        'no_current_contract';

    public const ACTION_NEEDS_MORE_EVIDENCE =
        'needs_more_evidence';

    public function __construct(
        private readonly CommercialAgreementCoverageReviewQueueService $queue,
        private readonly CommercialAgreementAssertionService $agreements,
        private readonly CommercialAgreementCoverageReviewService $coverageReviews,
    ) {}

    public function preview(
        string $clientServiceId,
        CarbonImmutable $asOf
    ): CommercialAgreementCoverageReviewCandidate {
        $candidate =
            $this->queue
                ->ready(
                    $asOf
                )
                ->first(
                    fn (
                        CommercialAgreementCoverageReviewCandidate $candidate
                    ) => $candidate->clientServiceId
                        === $clientServiceId
                );

        if (
            $candidate === null
        ) {
            throw ValidationException::withMessages([
                'client_service' => 'This canonical service is not currently unresolved in the contract coverage review queue.',
            ]);
        }

        return $candidate;
    }

    /**
     * Establish the first human-confirmed contractual assertion and
     * close coverage against exactly that assertion atomically.
     *
     * @return array{
     *     agreement: CommercialAgreement,
     *     coverage_review: CommercialAgreementCoverageReview
     * }
     */
    public function establishTerms(
        string $clientServiceId,
        string $cadence,
        int $contractedAmountPence,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?CarbonImmutable $effectiveTo = null,
        ?CarbonImmutable $renewsOn = null,
        ?string $sourceReference = null
    ): array {
        return DB::transaction(
            function () use (
                $clientServiceId,
                $cadence,
                $contractedAmountPence,
                $effectiveFrom,
                $reviewedBy,
                $source,
                $reason,
                $effectiveTo,
                $renewsOn,
                $sourceReference
            ): array {
                $candidate =
                    $this->preview(
                        clientServiceId: $clientServiceId,

                        asOf: $effectiveFrom
                    );

                $this->assertActionAvailable(
                    candidate: $candidate,

                    action: self::ACTION_ESTABLISH_TERMS
                );

                $agreement =
                    $this->agreements
                        ->confirm(
                            clientServiceId: $clientServiceId,

                            cadence: $cadence,

                            contractedAmountPence: $contractedAmountPence,

                            effectiveFrom: $effectiveFrom,

                            reviewedBy: $reviewedBy,

                            source: $source,

                            reason: $reason,

                            effectiveTo: $effectiveTo,

                            renewsOn: $renewsOn,

                            sourceReference: $sourceReference
                        );

                $coverageReview =
                    $this->coverageReviews
                        ->confirmTerms(
                            clientServiceId: $clientServiceId,

                            commercialAgreementId: $agreement->id,

                            effectiveFrom: $effectiveFrom,

                            reviewedBy: $reviewedBy,

                            source: $source,

                            reason: $reason,

                            sourceReference: $sourceReference,

                            evidenceSnapshot: $this->evidenceSnapshot(
                                $candidate
                            )
                        );

                return [
                    'agreement' => $agreement,

                    'coverage_review' => $coverageReview,
                ];
            }
        );
    }

    public function confirmCurrentTerms(
        string $clientServiceId,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?string $sourceReference = null
    ): CommercialAgreementCoverageReview {
        $candidate =
            $this->preview(
                clientServiceId: $clientServiceId,

                asOf: $effectiveFrom
            );

        $this->assertActionAvailable(
            candidate: $candidate,

            action: self::ACTION_CONFIRM_TERMS
        );

        if (
            $candidate->currentAgreementId
            === null
            || $candidate->currentAgreementStatus
                !== 'confirmed'
        ) {
            throw ValidationException::withMessages([
                'commercial_agreement' => 'There is no current confirmed commercial agreement to review.',
            ]);
        }

        return $this->coverageReviews
            ->confirmTerms(
                clientServiceId: $clientServiceId,

                commercialAgreementId: $candidate
                    ->currentAgreementId,

                effectiveFrom: $effectiveFrom,

                reviewedBy: $reviewedBy,

                source: $source,

                reason: $reason,

                sourceReference: $sourceReference,

                evidenceSnapshot: $this->evidenceSnapshot(
                    $candidate
                )
            );
    }

    public function confirmNoCurrentContract(
        string $clientServiceId,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?string $sourceReference = null
    ): CommercialAgreementCoverageReview {
        $candidate =
            $this->preview(
                clientServiceId: $clientServiceId,

                asOf: $effectiveFrom
            );

        $this->assertActionAvailable(
            candidate: $candidate,

            action: self::ACTION_NO_CURRENT_CONTRACT
        );

        return $this->coverageReviews
            ->confirmNoCurrentContract(
                clientServiceId: $clientServiceId,

                effectiveFrom: $effectiveFrom,

                reviewedBy: $reviewedBy,

                source: $source,

                reason: $reason,

                sourceReference: $sourceReference,

                evidenceSnapshot: $this->evidenceSnapshot(
                    $candidate
                )
            );
    }

    public function defer(
        string $clientServiceId,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?string $sourceReference = null
    ): CommercialAgreementCoverageReview {
        $candidate =
            $this->preview(
                clientServiceId: $clientServiceId,

                asOf: $effectiveFrom
            );

        $this->assertActionAvailable(
            candidate: $candidate,

            action: self::ACTION_NEEDS_MORE_EVIDENCE
        );

        return $this->coverageReviews
            ->defer(
                clientServiceId: $clientServiceId,

                effectiveFrom: $effectiveFrom,

                reviewedBy: $reviewedBy,

                source: $source,

                reason: $reason,

                sourceReference: $sourceReference,

                evidenceSnapshot: $this->evidenceSnapshot(
                    $candidate
                )
            );
    }

    private function assertActionAvailable(
        CommercialAgreementCoverageReviewCandidate $candidate,
        string $action
    ): void {
        if (
            ! in_array(
                $action,
                $candidate->availableDecisions,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'decision' => 'That human review action is not valid for the current contractual state.',
            ]);
        }
    }

    private function evidenceSnapshot(
        CommercialAgreementCoverageReviewCandidate $candidate
    ): array {
        return [
            'review_queue' => [
                'priority' => $candidate->priority,

                'priority_reason' => $candidate->priorityReason,

                'coverage_state' => $candidate->coverageState,

                'observed_billing_state' => $candidate->observedBillingState,

                'observed_evidence_count' => $candidate->observedEvidenceCount,

                'observed_cadence' => $candidate->observedCadence,

                'observed_freshness' => $candidate->observedFreshness,

                'first_observed_on' => $candidate->firstObservedOn,

                'last_observed_on' => $candidate->lastObservedOn,

                'observed_current_monthly_equivalent' => $candidate
                    ->observedCurrentMonthlyEquivalent,

                'current_agreement_id' => $candidate->currentAgreementId,

                'current_agreement_status' => $candidate->currentAgreementStatus,

                'current_agreement_cadence' => $candidate->currentAgreementCadence,

                'current_agreement_amount_pence' => $candidate
                    ->currentAgreementAmountPence,

                'current_agreement_monthly_equivalent' => $candidate
                    ->currentAgreementMonthlyEquivalent,
            ],
        ];
    }
}
