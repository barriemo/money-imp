<?php

namespace App\Domains\TeamTruth\Services;

use App\Models\ContractedCapacityAssertion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use LogicException;

final class ContractedCapacityCurrentAssertionService
{
    /**
     * @return Collection<int, ContractedCapacityAssertion>
     */
    public function all(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            ContractedCapacityAssertion::query()
                ->orderBy(
                    'created_at'
                )
                ->get();

        return $this->resolve(
            assertions: $assertions,
            asOf: $asOf
        );
    }

    public function forUser(
        User $user,
        ?CarbonImmutable $asOf = null
    ): ?ContractedCapacityAssertion {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            ContractedCapacityAssertion::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->orderBy(
                    'created_at'
                )
                ->get();

        return $this->resolve(
            assertions: $assertions,
            asOf: $asOf
        )->first();
    }

    /**
     * @param  Collection<int, ContractedCapacityAssertion>  $assertions
     * @return Collection<int, ContractedCapacityAssertion>
     */
    private function resolve(
        Collection $assertions,
        CarbonImmutable $asOf
    ): Collection {
        return $assertions
            ->groupBy(
                fn (
                    ContractedCapacityAssertion $assertion
                ) => (string) $assertion
                    ->user_id
            )
            ->map(
                function (
                    Collection $history
                ) use (
                    $asOf
                ): ?ContractedCapacityAssertion {
                    $eligible =
                        $history
                            ->filter(
                                fn (
                                    ContractedCapacityAssertion $assertion
                                ) => $this->effectiveOn(
                                    assertion: $assertion,
                                    asOf: $asOf
                                )
                            )
                            ->values();

                    if (
                        $eligible->isEmpty()
                    ) {
                        return null;
                    }

                    $supersededIds =
                        $eligible
                            ->pluck(
                                'supersedes_contracted_capacity_assertion_id'
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
                                    ContractedCapacityAssertion $assertion
                                ) => $supersededIds->has(
                                    (string) $assertion->id
                                )
                            )
                            ->values();

                    if (
                        $heads->count()
                        !== 1
                    ) {
                        throw new LogicException(
                            'Contracted capacity history does not resolve to exactly one current assertion for a User.'
                        );
                    }

                    return $heads->first();
                }
            )
            ->filter()
            ->values();
    }

    private function effectiveOn(
        ContractedCapacityAssertion $assertion,
        CarbonImmutable $asOf
    ): bool {
        $starts =
            CarbonImmutable::instance(
                $assertion->effective_from
            );

        if (
            $starts->gt(
                $asOf
            )
        ) {
            return false;
        }

        if (
            $assertion->effective_to
            === null
        ) {
            return true;
        }

        return CarbonImmutable::instance(
            $assertion->effective_to
        )->gte(
            $asOf
        );
    }
}
