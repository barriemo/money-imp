<?php

namespace App\Domains\CommercialTruth\Services;

use App\Models\CommercialAgreement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use LogicException;

final class CommercialAgreementCurrentAssertionService
{
    /**
     * @return Collection<int, CommercialAgreement>
     */
    public function all(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??=
            CarbonImmutable::today();

        $agreements =
            CommercialAgreement::query()
                ->orderBy(
                    'created_at'
                )
                ->get();

        return $this->resolve(
            agreements: $agreements,
            asOf: $asOf
        );
    }

    public function forService(
        string $clientServiceId,
        ?CarbonImmutable $asOf = null
    ): ?CommercialAgreement {
        $asOf ??=
            CarbonImmutable::today();

        $agreements =
            CommercialAgreement::query()
                ->where(
                    'client_service_id',
                    $clientServiceId
                )
                ->orderBy(
                    'created_at'
                )
                ->get();

        return $this->resolve(
            agreements: $agreements,
            asOf: $asOf
        )->first();
    }

    /**
     * @param  Collection<int, CommercialAgreement>  $agreements
     * @return Collection<int, CommercialAgreement>
     */
    private function resolve(
        Collection $agreements,
        CarbonImmutable $asOf
    ): Collection {
        return $agreements
            ->groupBy(
                fn (
                    CommercialAgreement $agreement
                ) => (string) $agreement
                    ->client_service_id
            )
            ->map(
                function (
                    Collection $history
                ) use (
                    $asOf
                ): ?CommercialAgreement {
                    $eligible =
                        $history
                            ->filter(
                                fn (
                                    CommercialAgreement $agreement
                                ) => $this->effectiveOn(
                                    agreement: $agreement,
                                    asOf: $asOf
                                )
                            )
                            ->values();

                    if (
                        $eligible->isEmpty()
                    ) {
                        return null;
                    }

                    /*
                     * Only an eligible successor supersedes another
                     * assertion for this as-of date.
                     *
                     * Therefore future agreed terms do not prematurely
                     * replace today's terms.
                     */
                    $supersededIds =
                        $eligible
                            ->pluck(
                                'supersedes_commercial_agreement_id'
                            )
                            ->filter()
                            ->map(
                                fn ($id) => (string) $id
                            )
                            ->flip();

                    $heads =
                        $eligible
                            ->reject(
                                fn (
                                    CommercialAgreement $agreement
                                ) => $supersededIds->has(
                                    (string) $agreement->id
                                )
                            )
                            ->values();

                    if (
                        $heads->count()
                        !== 1
                    ) {
                        throw new LogicException(
                            'Commercial agreement history does not resolve to exactly one current assertion for a canonical service.'
                        );
                    }

                    return $heads->first();
                }
            )
            ->filter()
            ->values();
    }

    private function effectiveOn(
        CommercialAgreement $agreement,
        CarbonImmutable $asOf
    ): bool {
        $starts =
            CarbonImmutable::instance(
                $agreement->effective_from
            );

        if (
            $starts->gt(
                $asOf
            )
        ) {
            return false;
        }

        if (
            $agreement->effective_to
            === null
        ) {
            return true;
        }

        return CarbonImmutable::instance(
            $agreement->effective_to
        )->gte(
            $asOf
        );
    }
}
