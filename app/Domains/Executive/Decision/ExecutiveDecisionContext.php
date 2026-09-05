<?php

namespace App\Domains\Executive\Decision;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttention;
use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Commercial\Decision\CommercialDecision;
use App\Domains\Delivery\Decision\DeliveryDecision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ExecutiveDecisionContext
{
    public function __construct(
        public ExecutiveDecisionRequest $request,
        public BusinessState $state,
        public BusinessStateBaseline $current,
        public ?BusinessStateBaseline $previous,
        public Collection $changes,
        public Collection $attention,
        public Collection $explanations,
        public ?CfoDecision $cfoDecision,
        public ?CommercialDecision $commercialDecision,
        public ?DeliveryDecision $deliveryDecision,
    ) {
        if (
            ! $this->state->asOf->equalTo(
                $this->current->asOf
            )
        ) {
            throw new InvalidArgumentException(
                'Executive decision context state and current baseline must describe the same temporal observation.'
            );
        }

        if (
            $this->previous !== null
            && ! $this->previous->asOf->lessThan(
                $this->current->asOf
            )
        ) {
            throw new InvalidArgumentException(
                'Executive decision context previous baseline must be earlier than the current observation.'
            );
        }

        if (
            ! $this->changes->every(
                fn (mixed $item): bool => $item instanceof BusinessStateChange
            )
        ) {
            throw new InvalidArgumentException(
                'Executive decision context changes must contain only business-state changes.'
            );
        }

        if (
            ! $this->attention->every(
                fn (mixed $item): bool => $item instanceof BusinessStateChangeAttention
            )
        ) {
            throw new InvalidArgumentException(
                'Executive decision context attention must contain only business-state change attention.'
            );
        }

        if (
            ! $this->explanations->every(
                fn (mixed $item): bool => $item instanceof BusinessStateExplanation
            )
        ) {
            throw new InvalidArgumentException(
                'Executive decision context explanations must contain only business-state explanations.'
            );
        }

        if ($this->previous === null) {
            if (
                $this->changes->isNotEmpty()
                || $this->attention->isNotEmpty()
                || $this->explanations->isNotEmpty()
            ) {
                throw new InvalidArgumentException(
                    'Executive decision context cannot contain temporal comparison results without a previous baseline.'
                );
            }
        } else {
            foreach ($this->changes as $change) {
                if (
                    ! $change->currentAsOf->equalTo(
                        $this->current->asOf
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Executive decision context change current timestamp must match the current observation.'
                    );
                }

                if (
                    ! $change->previousAsOf->equalTo(
                        $this->previous->asOf
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Executive decision context change previous timestamp must match the comparison baseline.'
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
                        'Executive decision context attention must refer to a change in the same context.'
                    );
                }
            }

            if (
                $this->explanations->count()
                !== $this->changes->count()
            ) {
                throw new InvalidArgumentException(
                    'Executive decision context requires exactly one explanation result for every detected change.'
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
                        'Executive decision context explanations must map exactly once to each detected change.'
                    );
                }
            }
        }

        $this->guardCfoDecision();
        $this->guardCommercialDecision();
        $this->guardDeliveryDecision();
    }

    public function hasComparisonBaseline(): bool
    {
        return $this->previous !== null;
    }

    public function asOf(): CarbonImmutable
    {
        return $this->state->asOf;
    }

    private function guardCfoDecision(): void
    {
        if ($this->request->cfoRequest === null) {
            if ($this->cfoDecision !== null) {
                throw new InvalidArgumentException(
                    'Executive decision context cannot contain a CFO decision that was not explicitly requested.'
                );
            }

            return;
        }

        if ($this->cfoDecision === null) {
            throw new InvalidArgumentException(
                'Executive decision context requires the explicitly requested CFO decision.'
            );
        }

        if (
            $this->cfoDecision->key
            !== $this->request->cfoRequest->key
        ) {
            throw new InvalidArgumentException(
                'Executive decision context CFO decision key must match the requested CFO decision.'
            );
        }

        if (
            $this->cfoDecision->question
            !== $this->request->cfoRequest->question
        ) {
            throw new InvalidArgumentException(
                'Executive decision context CFO decision question must match the requested CFO decision.'
            );
        }
    }

    private function guardCommercialDecision(): void
    {
        if ($this->request->commercialRequest === null) {
            if ($this->commercialDecision !== null) {
                throw new InvalidArgumentException(
                    'Executive decision context cannot contain a Commercial decision that was not explicitly requested.'
                );
            }

            return;
        }

        if ($this->commercialDecision === null) {
            throw new InvalidArgumentException(
                'Executive decision context requires the explicitly requested Commercial decision.'
            );
        }

        if (
            $this->commercialDecision->key
            !== $this->request->commercialRequest->key
        ) {
            throw new InvalidArgumentException(
                'Executive decision context Commercial decision key must match the requested Commercial decision.'
            );
        }

        if (
            $this->commercialDecision->question
            !== $this->request->commercialRequest->question
        ) {
            throw new InvalidArgumentException(
                'Executive decision context Commercial decision question must match the requested Commercial decision.'
            );
        }
    }

    private function guardDeliveryDecision(): void
    {
        if ($this->request->deliveryRequest === null) {
            if ($this->deliveryDecision !== null) {
                throw new InvalidArgumentException(
                    'Executive decision context cannot contain a Delivery decision that was not explicitly requested.'
                );
            }

            return;
        }

        if ($this->deliveryDecision === null) {
            throw new InvalidArgumentException(
                'Executive decision context requires the explicitly requested Delivery decision.'
            );
        }

        if (
            $this->deliveryDecision->key
            !== $this->request->deliveryRequest->key
        ) {
            throw new InvalidArgumentException(
                'Executive decision context Delivery decision key must match the requested Delivery decision.'
            );
        }

        if (
            $this->deliveryDecision->question
            !== $this->request->deliveryRequest->question
        ) {
            throw new InvalidArgumentException(
                'Executive decision context Delivery decision question must match the requested Delivery decision.'
            );
        }
    }
}
