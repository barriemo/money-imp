<?php

namespace App\Domains\TeamTruth\Services;

use App\Models\TeamMembershipAssertion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use LogicException;

final class TeamMembershipCurrentAssertionService
{
    /**
     * @return Collection<int, TeamMembershipAssertion>
     */
    public function all(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            TeamMembershipAssertion::query()
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
    ): ?TeamMembershipAssertion {
        $asOf ??=
            CarbonImmutable::today();

        $assertions =
            TeamMembershipAssertion::query()
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
     * @param  Collection<int, TeamMembershipAssertion>  $assertions
     * @return Collection<int, TeamMembershipAssertion>
     */
    private function resolve(
        Collection $assertions,
        CarbonImmutable $asOf
    ): Collection {
        return $assertions
            ->groupBy(
                fn (
                    TeamMembershipAssertion $assertion
                ) => (string) $assertion
                    ->user_id
            )
            ->map(
                function (
                    Collection $history
                ) use (
                    $asOf
                ): ?TeamMembershipAssertion {
                    $this->assertValidHistory(
                        $history
                    );

                    $eligible =
                        $history
                            ->filter(
                                fn (
                                    TeamMembershipAssertion $assertion
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
                                'supersedes_team_membership_assertion_id'
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
                                    TeamMembershipAssertion $assertion
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
                            'Team membership history does not resolve to exactly one current assertion for a User.'
                        );
                    }

                    return $heads->first();
                }
            )
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, TeamMembershipAssertion>  $history
     */
    private function assertValidHistory(
        Collection $history
    ): void {
        $byId =
            $history->keyBy(
                fn (
                    TeamMembershipAssertion $assertion
                ) => (string) $assertion->id
            );

        foreach (
            $history as $assertion
        ) {
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
                    'Team membership assertion has an invalid effective date range.'
                );
            }

            $supersedes =
                $assertion
                    ->supersedes_team_membership_assertion_id;

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
                    'A team-membership assertion may supersede only an assertion for the same User.'
                );
            }
        }
    }

    private function effectiveOn(
        TeamMembershipAssertion $assertion,
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
