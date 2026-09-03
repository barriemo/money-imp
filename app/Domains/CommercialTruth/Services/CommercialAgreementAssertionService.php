<?php

namespace App\Domains\CommercialTruth\Services;

use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommercialAgreementAssertionService
{
    private const CADENCES = [
        'monthly',
        'quarterly',
        'annual',
        'one_off',
    ];

    public function confirm(
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
    ): CommercialAgreement {
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
            ): CommercialAgreement {
                $service =
                    $this->lockService(
                        $clientServiceId
                    );

                if (
                    CommercialAgreement::query()
                        ->where(
                            'client_service_id',
                            $service->id
                        )
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'client_service' => 'This canonical service already has commercial agreement history. Create a superseding assertion instead.',
                    ]);
                }

                return $this->createConfirmed(
                    service: $service,
                    supersedes: null,
                    cadence: $cadence,
                    contractedAmountPence: $contractedAmountPence,
                    effectiveFrom: $effectiveFrom,
                    effectiveTo: $effectiveTo,
                    renewsOn: $renewsOn,
                    reviewedBy: $reviewedBy,
                    source: $source,
                    sourceReference: $sourceReference,
                    reason: $reason
                );
            }
        );
    }

    public function supersede(
        string $commercialAgreementId,
        string $cadence,
        int $contractedAmountPence,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?CarbonImmutable $effectiveTo = null,
        ?CarbonImmutable $renewsOn = null,
        ?string $sourceReference = null
    ): CommercialAgreement {
        return DB::transaction(
            function () use (
                $commercialAgreementId,
                $cadence,
                $contractedAmountPence,
                $effectiveFrom,
                $reviewedBy,
                $source,
                $reason,
                $effectiveTo,
                $renewsOn,
                $sourceReference
            ): CommercialAgreement {
                $previous =
                    $this->lockHeadAssertion(
                        $commercialAgreementId
                    );

                $this->assertConfirmedHead(
                    $previous
                );

                $service =
                    $this->lockService(
                        $previous
                            ->client_service_id
                    );

                $this->assertSuccessorDate(
                    previous: $previous,
                    effectiveFrom: $effectiveFrom
                );

                return $this->createConfirmed(
                    service: $service,
                    supersedes: $previous,
                    cadence: $cadence,
                    contractedAmountPence: $contractedAmountPence,
                    effectiveFrom: $effectiveFrom,
                    effectiveTo: $effectiveTo,
                    renewsOn: $renewsOn,
                    reviewedBy: $reviewedBy,
                    source: $source,
                    sourceReference: $sourceReference,
                    reason: $reason
                );
            }
        );
    }

    public function terminate(
        string $commercialAgreementId,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?string $sourceReference = null
    ): CommercialAgreement {
        return DB::transaction(
            function () use (
                $commercialAgreementId,
                $effectiveFrom,
                $reviewedBy,
                $source,
                $reason,
                $sourceReference
            ): CommercialAgreement {
                $previous =
                    $this->lockHeadAssertion(
                        $commercialAgreementId
                    );

                $this->assertConfirmedHead(
                    $previous
                );

                $service =
                    $this->lockService(
                        $previous
                            ->client_service_id
                    );

                $this->assertSuccessorDate(
                    previous: $previous,
                    effectiveFrom: $effectiveFrom
                );

                $reviewer =
                    $this->reviewer(
                        $reviewedBy
                    );

                $source =
                    $this->requiredText(
                        $source,
                        'source'
                    );

                $reason =
                    $this->requiredText(
                        $reason,
                        'reason'
                    );

                $sourceReference =
                    $this->optionalText(
                        $sourceReference
                    );

                $snapshot = [
                    'client_id' => $service->client_id,

                    'client_service_id' => $service->id,

                    'client_service_name' => $service->name,

                    'status' => 'terminated',

                    'cadence' => null,

                    'contracted_amount_pence' => null,

                    'currency' => 'GBP',

                    'monthly_equivalent' => null,

                    'effective_from' => $effectiveFrom
                        ->toDateString(),

                    'effective_to' => null,

                    'renews_on' => null,

                    'source' => $source,

                    'source_reference' => $sourceReference,

                    'reviewed_by' => $reviewer->id,

                    'reviewed_by_name' => $reviewer->name,
                ];

                return CommercialAgreement::create([
                    'client_id' => $service->client_id,

                    'client_service_id' => $service->id,

                    'supersedes_commercial_agreement_id' => $previous->id,

                    'status' => 'terminated',

                    'cadence' => null,

                    'contracted_amount_pence' => null,

                    'currency' => 'GBP',

                    'monthly_equivalent' => null,

                    'effective_from' => $effectiveFrom,

                    'effective_to' => null,

                    'renews_on' => null,

                    'source' => $source,

                    'source_reference' => $sourceReference,

                    'reviewed_by' => $reviewer->id,

                    'reviewed_by_name' => $reviewer->name,

                    'reviewed_at' => now(),

                    'reason' => $reason,

                    'terms_snapshot' => $snapshot,

                    'metadata' => [
                        'assertion_kind' => 'termination',
                    ],
                ]);
            }
        );
    }

    private function createConfirmed(
        ClientService $service,
        ?CommercialAgreement $supersedes,
        string $cadence,
        int $contractedAmountPence,
        CarbonImmutable $effectiveFrom,
        ?CarbonImmutable $effectiveTo,
        ?CarbonImmutable $renewsOn,
        int $reviewedBy,
        string $source,
        ?string $sourceReference,
        string $reason
    ): CommercialAgreement {
        $this->assertCadence(
            $cadence
        );

        if (
            $contractedAmountPence
            < 0
        ) {
            throw ValidationException::withMessages([
                'contracted_amount_pence' => 'A confirmed contractual amount cannot be negative.',
            ]);
        }

        if (
            $effectiveTo !== null
            && $effectiveTo->lt(
                $effectiveFrom
            )
        ) {
            throw ValidationException::withMessages([
                'effective_to' => 'The contractual end date cannot be before the effective start date.',
            ]);
        }

        if (
            $renewsOn !== null
            && $renewsOn->lt(
                $effectiveFrom
            )
        ) {
            throw ValidationException::withMessages([
                'renews_on' => 'The renewal date cannot be before the effective start date.',
            ]);
        }

        $reviewer =
            $this->reviewer(
                $reviewedBy
            );

        $source =
            $this->requiredText(
                $source,
                'source'
            );

        $reason =
            $this->requiredText(
                $reason,
                'reason'
            );

        $sourceReference =
            $this->optionalText(
                $sourceReference
            );

        $monthlyEquivalent =
            $this->monthlyEquivalent(
                cadence: $cadence,
                contractedAmountPence: $contractedAmountPence
            );

        $snapshot = [
            'client_id' => $service->client_id,

            'client_service_id' => $service->id,

            'client_service_name' => $service->name,

            'status' => 'confirmed',

            'cadence' => $cadence,

            'contracted_amount_pence' => $contractedAmountPence,

            'currency' => 'GBP',

            'monthly_equivalent' => $monthlyEquivalent,

            'effective_from' => $effectiveFrom
                ->toDateString(),

            'effective_to' => $effectiveTo
                ?->toDateString(),

            'renews_on' => $renewsOn
                ?->toDateString(),

            'source' => $source,

            'source_reference' => $sourceReference,

            'reviewed_by' => $reviewer->id,

            'reviewed_by_name' => $reviewer->name,
        ];

        return CommercialAgreement::create([
            'client_id' => $service->client_id,

            'client_service_id' => $service->id,

            'supersedes_commercial_agreement_id' => $supersedes?->id,

            'status' => 'confirmed',

            'cadence' => $cadence,

            'contracted_amount_pence' => $contractedAmountPence,

            'currency' => 'GBP',

            'monthly_equivalent' => $monthlyEquivalent,

            'effective_from' => $effectiveFrom,

            'effective_to' => $effectiveTo,

            'renews_on' => $renewsOn,

            'source' => $source,

            'source_reference' => $sourceReference,

            'reviewed_by' => $reviewer->id,

            'reviewed_by_name' => $reviewer->name,

            'reviewed_at' => now(),

            'reason' => $reason,

            'terms_snapshot' => $snapshot,

            'metadata' => [
                'assertion_kind' => $supersedes === null
                        ? 'initial_confirmation'
                        : 'supersession',
            ],
        ]);
    }

    private function assertConfirmedHead(
        CommercialAgreement $agreement
    ): void {
        if (
            $agreement->status
            !== 'confirmed'
        ) {
            throw ValidationException::withMessages([
                'commercial_agreement' => 'Only a current confirmed agreement assertion may be superseded or terminated.',
            ]);
        }
    }

    private function lockHeadAssertion(
        string $commercialAgreementId
    ): CommercialAgreement {
        $agreement =
            CommercialAgreement::query()
                ->lockForUpdate()
                ->findOrFail(
                    $commercialAgreementId
                );

        if (
            CommercialAgreement::query()
                ->where(
                    'supersedes_commercial_agreement_id',
                    $agreement->id
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'commercial_agreement' => 'This agreement assertion has already been superseded. Changes must be made from the current head assertion.',
            ]);
        }

        return $agreement;
    }

    private function lockService(
        string $clientServiceId
    ): ClientService {
        $service =
            ClientService::withTrashed()
                ->lockForUpdate()
                ->findOrFail(
                    $clientServiceId
                );

        if (
            $service->trashed()
        ) {
            throw ValidationException::withMessages([
                'client_service' => 'Contracted truth cannot be attached to a deleted canonical service.',
            ]);
        }

        return $service;
    }

    private function reviewer(
        int $reviewedBy
    ): User {
        return User::query()
            ->findOrFail(
                $reviewedBy
            );
    }

    private function assertCadence(
        string $cadence
    ): void {
        if (
            ! in_array(
                $cadence,
                self::CADENCES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'cadence' => 'Unsupported contractual cadence.',
            ]);
        }
    }

    private function assertSuccessorDate(
        CommercialAgreement $previous,
        CarbonImmutable $effectiveFrom
    ): void {
        if (
            $effectiveFrom->lt(
                CarbonImmutable::instance(
                    $previous->effective_from
                )
            )
        ) {
            throw ValidationException::withMessages([
                'effective_from' => 'A superseding assertion cannot become effective before the assertion it replaces.',
            ]);
        }
    }

    private function monthlyEquivalent(
        string $cadence,
        int $contractedAmountPence
    ): ?float {
        $amount =
            $contractedAmountPence
            / 100;

        return match ($cadence) {
            'monthly' => round(
                $amount,
                2
            ),

            'quarterly' => round(
                $amount / 3,
                2
            ),

            'annual' => round(
                $amount / 12,
                2
            ),

            'one_off' => null,
        };
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
