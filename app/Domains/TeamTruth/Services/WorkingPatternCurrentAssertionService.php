<?php

namespace App\Domains\TeamTruth\Services;

use App\Models\User;
use App\Models\WorkingPatternAssertion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use LogicException;

final class WorkingPatternCurrentAssertionService
{
    /**
     * @return Collection<int, WorkingPatternAssertion>
     */
    public function all(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            WorkingPatternAssertion::query()
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
    ): ?WorkingPatternAssertion {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            WorkingPatternAssertion::query()
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
     * @param  Collection<int, WorkingPatternAssertion>  $assertions
     * @return Collection<int, WorkingPatternAssertion>
     */
    private function resolve(
        Collection $assertions,
        CarbonImmutable $asOf
    ): Collection {
        return $assertions
            ->groupBy(
                fn (
                    WorkingPatternAssertion $assertion
                ) => (string) $assertion
                    ->user_id
            )
            ->map(
                function (
                    Collection $history
                ) use (
                    $asOf
                ): ?WorkingPatternAssertion {
                    $eligible =
                        $history
                            ->filter(
                                fn (
                                    WorkingPatternAssertion $assertion
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
                                'supersedes_working_pattern_assertion_id'
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
                                    WorkingPatternAssertion $assertion
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
                            'Working pattern history does not resolve to exactly one current assertion for a User.'
                        );
                    }

                    return $heads->first();
                }
            )
            ->filter()
            ->values();
    }

    private function effectiveOn(
        WorkingPatternAssertion $assertion,
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
