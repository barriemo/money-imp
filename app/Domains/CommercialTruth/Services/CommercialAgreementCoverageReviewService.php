<?php

namespace App\Domains\CommercialTruth\Services;

use App\Models\ClientService;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CommercialAgreementCoverageReviewService
{
    public function __construct(
        private readonly CommercialAgreementCurrentAssertionService $currentAgreements,
    ) {}

    public function confirmTerms(
        string $clientServiceId,
        string $commercialAgreementId,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?string $sourceReference = null,
        array $evidenceSnapshot = []
    ): CommercialAgreementCoverageReview {
        return $this->record(
            clientServiceId: $clientServiceId,

            outcome: CommercialAgreementCoverageService::OUTCOME_CONFIRMED_TERMS,

            commercialAgreementId: $commercialAgreementId,

            effectiveFrom: $effectiveFrom,

            reviewedBy: $reviewedBy,

            source: $source,

            reason: $reason,

            sourceReference: $sourceReference,

            evidenceSnapshot: $evidenceSnapshot
        );
    }

    public function confirmNoCurrentContract(
        string $clientServiceId,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?string $sourceReference = null,
        array $evidenceSnapshot = []
    ): CommercialAgreementCoverageReview {
        return $this->record(
            clientServiceId: $clientServiceId,

            outcome: CommercialAgreementCoverageService::OUTCOME_NO_CURRENT_CONTRACT,

            commercialAgreementId: null,

            effectiveFrom: $effectiveFrom,

            reviewedBy: $reviewedBy,

            source: $source,

            reason: $reason,

            sourceReference: $sourceReference,

            evidenceSnapshot: $evidenceSnapshot
        );
    }

    public function defer(
        string $clientServiceId,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?string $sourceReference = null,
        array $evidenceSnapshot = []
    ): CommercialAgreementCoverageReview {
        return $this->record(
            clientServiceId: $clientServiceId,

            outcome: CommercialAgreementCoverageService::OUTCOME_NEEDS_MORE_EVIDENCE,

            commercialAgreementId: null,

            effectiveFrom: $effectiveFrom,

            reviewedBy: $reviewedBy,

            source: $source,

            reason: $reason,

            sourceReference: $sourceReference,

            evidenceSnapshot: $evidenceSnapshot
        );
    }

    private function record(
        string $clientServiceId,
        string $outcome,
        ?string $commercialAgreementId,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?string $sourceReference,
        array $evidenceSnapshot
    ): CommercialAgreementCoverageReview {
        return DB::transaction(
            function () use (
                $clientServiceId,
                $outcome,
                $commercialAgreementId,
                $effectiveFrom,
                $reviewedBy,
                $source,
                $reason,
                $sourceReference,
                $evidenceSnapshot
            ): CommercialAgreementCoverageReview {
                $service =
                    $this->lockScopedService(
                        clientServiceId: $clientServiceId,

                        effectiveFrom: $effectiveFrom
                    );

                $previous =
                    $this->lockLatestHead(
                        $service->id
                    );

                if (
                    $previous !== null
                    && $effectiveFrom->lt(
                        CarbonImmutable::instance(
                            $previous->effective_from
                        )
                    )
                ) {
                    throw ValidationException::withMessages([
                        'effective_from' => 'A superseding coverage review cannot become effective before the review it replaces.',
                    ]);
                }

                $currentAgreement =
                    $this->currentAgreements
                        ->forService(
                            clientServiceId: $service->id,

                            asOf: $effectiveFrom
                        );

                if (
                    $outcome
                    === CommercialAgreementCoverageService::OUTCOME_CONFIRMED_TERMS
                ) {
                    if (
                        $commercialAgreementId
                        === null
                        || $currentAgreement
                            === null
                        || (string) $currentAgreement
                            ->id
                            !== $commercialAgreementId
                        || $currentAgreement
                            ->status
                            !== 'confirmed'
                    ) {
                        throw ValidationException::withMessages([
                            'commercial_agreement' => 'Confirmed contract coverage must reference the current confirmed commercial agreement assertion for this canonical service.',
                        ]);
                    }
                } elseif (
                    $commercialAgreementId
                    !== null
                ) {
                    throw ValidationException::withMessages([
                        'commercial_agreement' => 'Only confirmed_terms coverage may reference a commercial agreement.',
                    ]);
                }

                if (
                    $outcome
                    === CommercialAgreementCoverageService::OUTCOME_NO_CURRENT_CONTRACT
                    && $currentAgreement
                        !== null
                    && $currentAgreement
                        ->status
                        === 'confirmed'
                ) {
                    throw ValidationException::withMessages([
                        'outcome' => 'A service with current confirmed contractual terms cannot be reviewed as having no current contract.',
                    ]);
                }

                $reviewer =
                    User::query()
                        ->findOrFail(
                            $reviewedBy
                        );

                $source =
                    $this->requiredText(
                        value: $source,

                        field: 'source'
                    );

                $reason =
                    $this->requiredText(
                        value: $reason,

                        field: 'reason'
                    );

                $sourceReference =
                    $this->optionalText(
                        $sourceReference
                    );

                $snapshot = [
                    'client_id' => (string) $service
                        ->client_id,

                    'client_service_id' => (string) $service->id,

                    'client_service_name' => $service->name,

                    'service_status' => $service->status,

                    'outcome' => $outcome,

                    'commercial_agreement_id' => $commercialAgreementId,

                    'effective_from' => $effectiveFrom
                        ->toDateString(),

                    'current_agreement_status' => $currentAgreement?->status,

                    'current_agreement_cadence' => $currentAgreement?->cadence,

                    'current_agreement_amount_pence' => $currentAgreement
                        ?->contracted_amount_pence,

                    'current_agreement_monthly_equivalent' => $currentAgreement
                        ?->monthly_equivalent,

                    'reviewed_by' => $reviewer->id,

                    'reviewed_by_name' => $reviewer->name,

                    'evidence' => $evidenceSnapshot,
                ];

                return CommercialAgreementCoverageReview::create([
                    'client_id' => $service->client_id,

                    'client_service_id' => $service->id,

                    'supersedes_commercial_agreement_coverage_review_id' => $previous?->id,

                    'outcome' => $outcome,

                    'commercial_agreement_id' => $commercialAgreementId,

                    'effective_from' => $effectiveFrom,

                    'source' => $source,

                    'source_reference' => $sourceReference,

                    'reviewed_by' => $reviewer->id,

                    'reviewed_by_name' => $reviewer->name,

                    'reviewed_at' => now(),

                    'reason' => $reason,

                    'evidence_snapshot' => $snapshot,

                    'metadata' => [
                        'assertion_kind' => $previous === null
                                ? 'initial_coverage_review'
                                : 'coverage_supersession',
                    ],
                ]);
            }
        );
    }

    private function lockScopedService(
        string $clientServiceId,
        CarbonImmutable $effectiveFrom
    ): ClientService {
        $service =
            ClientService::withTrashed()
                ->lockForUpdate()
                ->findOrFail(
                    $clientServiceId
                );

        if (
            $service->trashed()
            || $service->status
                !== 'active'
        ) {
            throw ValidationException::withMessages([
                'client_service' => 'Commercial contract coverage may only be reviewed for an active canonical service.',
            ]);
        }

        if (
            $service->starts_on
            !== null
            && CarbonImmutable::instance(
                $service->starts_on
            )->gt(
                $effectiveFrom
            )
        ) {
            throw ValidationException::withMessages([
                'effective_from' => 'Coverage cannot become effective before the canonical service starts.',
            ]);
        }

        if (
            $service->ends_on
            !== null
            && CarbonImmutable::instance(
                $service->ends_on
            )->lt(
                $effectiveFrom
            )
        ) {
            throw ValidationException::withMessages([
                'effective_from' => 'Coverage cannot become effective after the canonical service has ended.',
            ]);
        }

        return $service;
    }

    private function lockLatestHead(
        string $clientServiceId
    ): ?CommercialAgreementCoverageReview {
        $reviews =
            CommercialAgreementCoverageReview::query()
                ->where(
                    'client_service_id',
                    $clientServiceId
                )
                ->lockForUpdate()
                ->get();

        if (
            $reviews->isEmpty()
        ) {
            return null;
        }

        $supersededIds =
            $reviews
                ->pluck(
                    'supersedes_commercial_agreement_coverage_review_id'
                )
                ->filter()
                ->map(
                    fn ($id) => (string) $id
                )
                ->flip();

        $heads =
            $reviews
                ->reject(
                    fn (
                        CommercialAgreementCoverageReview $review
                    ) => $supersededIds->has(
                        (string) $review->id
                    )
                )
                ->values();

        if (
            $heads->count()
            !== 1
        ) {
            throw new LogicException(
                'Commercial agreement coverage history does not resolve to exactly one latest review.'
            );
        }

        return $heads->first();
    }

    private function requiredText(
        string $value,
        string $field
    ): string {
        $value =
            trim(
                $value
            );

        if (
            $value === ''
        ) {
            throw ValidationException::withMessages([
                $field => ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        $field
                    )
                )
                    .' is required.',
            ]);
        }

        return $value;
    }

    private function optionalText(
        ?string $value
    ): ?string {
        if (
            $value === null
        ) {
            return null;
        }

        $value =
            trim(
                $value
            );

        return $value !== ''
            ? $value
            : null;
    }
}
