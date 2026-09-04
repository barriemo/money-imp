<?php

namespace App\Domains\BusinessBrain\BusinessState\Explanation;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BusinessStateExplanationReport
{
    public function __construct(
        public BusinessStateBaseline $current,
        public ?BusinessStateBaseline $previous,
        public Collection $explanations,
    ) {
        if (
            ! $this->explanations->every(
                fn (mixed $item): bool => $item instanceof BusinessStateExplanation
            )
        ) {
            throw new InvalidArgumentException(
                'Explanation report must contain only business-state explanations.'
            );
        }
    }

    public function hasComparisonBaseline(): bool
    {
        return $this->previous !== null;
    }
}
