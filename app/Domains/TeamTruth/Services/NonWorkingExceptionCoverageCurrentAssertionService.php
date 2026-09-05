<?php

namespace App\Domains\TeamTruth\Services;

use App\Models\NonWorkingExceptionCoverageAssertion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use LogicException;

final class NonWorkingExceptionCoverageCurrentAssertionService
{
    /**
     * @return Collection<int, NonWorkingExceptionCoverageAssertion>
     */
    public function all(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            NonWorkingExceptionCoverageAssertion::query()
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
    ): ?NonWorkingExceptionCoverageAssertion {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            NonWorkingExceptionCoverageAssertion::query()
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
     * @param  Collection<int, NonWorkingExceptionCoverageAssertion>  $assertions
     * @return Collection<int, NonWorkingExceptionCoverageAssertion>
     */
    private function resolve(
        Collection $assertions,
        CarbonImmutable $asOf
    ): Collection {
        return $assertions
            ->groupBy(
                fn (
                    NonWorkingExceptionCoverageAssertion $assertion
                ) => (string) $assertion->user_id
            )
            ->map(
                function (
                    Collection $history
                ) use (
                    $asOf
                ): ?NonWorkingExceptionCoverageAssertion {
                    $this->assertValidHistory(
                        $history
                    );

                    $eligible =
                        $history
                            ->filter(
                                fn (
                                    NonWorkingExceptionCoverageAssertion $assertion
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
                                'supersedes_non_working_exception_coverage_assertion_id'
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
                                    NonWorkingExceptionCoverageAssertion $assertion
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
                            'Non-working exception coverage history does not resolve to exactly one current assertion for a User.'
                        );
                    }

                    return $heads->first();
                }
            )
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, NonWorkingExceptionCoverageAssertion>  $history
     */
    private function assertValidHistory(
        Collection $history
    ): void {
        $byId =
            $history->keyBy(
                fn (
                    NonWorkingExceptionCoverageAssertion $assertion
                ) => (string) $assertion->id
            );

        foreach (
            $history as $assertion
        ) {
            $coveredFrom =
                CarbonImmutable::instance(
                    $assertion->covered_from
                );

            $coveredTo =
                CarbonImmutable::instance(
                    $assertion->covered_to
                );

            if (
                $coveredTo->lt(
                    $coveredFrom
                )
            ) {
                throw new LogicException(
                    'Non-working exception coverage assertion has an invalid covered date range.'
                );
            }

            $starts =
                CarbonImmutable::instance(
                    $assertion->effective_from
                );

            if (
                $assertion->effective_to
                !== null
                && CarbonImmutable::instance(
                    $assertion->effective_to
                )->lt(
                    $starts
                )
            ) {
                throw new LogicException(
                    'Non-working exception coverage assertion has an invalid effective date range.'
                );
            }

            if (
                ! in_array(
                    $assertion->coverage_status,
                    [
                        NonWorkingExceptionCoverageAssertion::STATUS_COMPLETE,
                        NonWorkingExceptionCoverageAssertion::STATUS_NOT_COMPLETE,
                    ],
                    true
                )
            ) {
                throw new LogicException(
                    'Unsupported non-working-exception coverage status.'
                );
            }

            $supersedes =
                $assertion
                    ->supersedes_non_working_exception_coverage_assertion_id;

            if (
                $supersedes === null
            ) {
                continue;
            }

            if (
                ! $byId->has(
                    (string) $supersedes
                )
            ) {
                throw new LogicException(
                    'A non-working-exception coverage assertion may supersede only coverage for the same User.'
                );
            }
        }
    }

    private function effectiveOn(
        NonWorkingExceptionCoverageAssertion $assertion,
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
