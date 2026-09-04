<?php

namespace App\Domains\Cfo\Decision;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttention;
use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CfoDecisionContext
{
    public function __construct(
        public CfoDecisionRequest $request,
        public BusinessState $state,
        public BusinessStateBaseline $current,
        public ?BusinessStateBaseline $previous,
        public Collection $changes,
        public Collection $attention,
        public Collection $explanations,
    ) {
        if (
            ! $this->state->asOf->equalTo(
                $this->current->asOf
            )
        ) {
            throw new InvalidArgumentException(
                'CFO decision context state and current baseline must describe the same temporal observation.'
            );
        }

        if (
            $this->previous !== null
            && ! $this->previous->asOf->lessThan(
                $this->current->asOf
            )
        ) {
            throw new InvalidArgumentException(
                'CFO decision context previous baseline must be earlier than the current observation.'
            );
        }

        if (
            ! $this->changes->every(
                fn (mixed $item): bool => $item instanceof BusinessStateChange
            )
        ) {
            throw new InvalidArgumentException(
                'CFO decision context changes must contain only business-state changes.'
            );
        }

        if (
            ! $this->attention->every(
                fn (mixed $item): bool => $item instanceof BusinessStateChangeAttention
            )
        ) {
            throw new InvalidArgumentException(
                'CFO decision context attention must contain only business-state change attention.'
            );
        }

        if (
            ! $this->explanations->every(
                fn (mixed $item): bool => $item instanceof BusinessStateExplanation
            )
        ) {
            throw new InvalidArgumentException(
                'CFO decision context explanations must contain only business-state explanations.'
            );
        }

        if ($this->previous === null) {
            if (
                $this->changes->isNotEmpty()
                || $this->attention->isNotEmpty()
                || $this->explanations->isNotEmpty()
            ) {
                throw new InvalidArgumentException(
                    'CFO decision context cannot contain temporal comparison results without a previous baseline.'
                );
            }

            return;
        }

        foreach ($this->changes as $change) {
            if (
                ! $change->currentAsOf->equalTo(
                    $this->current->asOf
                )
            ) {
                throw new InvalidArgumentException(
                    'CFO decision context change current timestamp must match the current observation.'
                );
            }

            if (
                ! $change->previousAsOf->equalTo(
                    $this->previous->asOf
                )
            ) {
                throw new InvalidArgumentException(
                    'CFO decision context change previous timestamp must match the comparison baseline.'
                );
            }
        }

        foreach ($this->attention as $item) {
            $belongsToChange =
                $this->changes->contains(
                    fn (BusinessStateChange $change): bool => $change === $item->change
                );

            if (! $belongsToChange) {
                throw new InvalidArgumentException(
                    'CFO decision context attention must refer to a change in the same context.'
                );
            }
        }

        if (
            $this->explanations->count()
            !== $this->changes->count()
        ) {
            throw new InvalidArgumentException(
                'CFO decision context requires exactly one explanation result for every detected change.'
            );
        }

        foreach ($this->changes as $change) {
            $matches =
                $this->explanations
                    ->filter(
                        fn (BusinessStateExplanation $explanation): bool => $explanation->observation === $change
                    )
                    ->count();

            if ($matches !== 1) {
                throw new InvalidArgumentException(
                    'CFO decision context explanations must map exactly once to each detected change.'
                );
            }
        }
    }

    public function hasComparisonBaseline(): bool
    {
        return $this->previous !== null;
    }

    public function asOf(): CarbonImmutable
    {
        return $this->state->asOf;
    }
}
