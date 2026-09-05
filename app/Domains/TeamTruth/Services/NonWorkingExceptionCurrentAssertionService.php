<?php

namespace App\Domains\TeamTruth\Services;

use App\Models\NonWorkingExceptionAssertion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use LogicException;

final class NonWorkingExceptionCurrentAssertionService
{
    /**
     * @return Collection<int, NonWorkingExceptionAssertion>
     */
    public function all(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            NonWorkingExceptionAssertion::query()
                ->orderBy(
                    'created_at'
                )
                ->get();

        return $this->resolve(
            assertions: $assertions,
            asOf: $asOf
        );
    }

    /**
     * A user may have many independent current exception chains.
     *
     * @return Collection<int, NonWorkingExceptionAssertion>
     */
    public function forUser(
        User $user,
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            NonWorkingExceptionAssertion::query()
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
        );
    }

    public function forException(
        User $user,
        string $exceptionKey,
        ?CarbonImmutable $asOf = null
    ): ?NonWorkingExceptionAssertion {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            NonWorkingExceptionAssertion::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'exception_key',
                    $exceptionKey
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
     * @param  Collection<int, NonWorkingExceptionAssertion>  $assertions
     * @return Collection<int, NonWorkingExceptionAssertion>
     */
    private function resolve(
        Collection $assertions,
        CarbonImmutable $asOf
    ): Collection {
        return $assertions
            ->groupBy(
                fn (
                    NonWorkingExceptionAssertion $assertion
                ) => (string) $assertion->user_id
                    .'|'
                    .(string) $assertion->exception_key
            )
            ->map(
                function (
                    Collection $history
                ) use (
                    $asOf
                ): ?NonWorkingExceptionAssertion {
                    $eligible =
                        $history
                            ->filter(
                                fn (
                                    NonWorkingExceptionAssertion $assertion
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
                                'supersedes_non_working_exception_assertion_id'
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
                                    NonWorkingExceptionAssertion $assertion
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
                            'Non-working exception history does not resolve to exactly one current assertion for an exception chain.'
                        );
                    }

                    return $heads->first();
                }
            )
            ->filter()
            ->values();
    }

    private function effectiveOn(
        NonWorkingExceptionAssertion $assertion,
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
