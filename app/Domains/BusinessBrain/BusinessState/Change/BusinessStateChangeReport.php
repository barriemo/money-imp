<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use Illuminate\Support\Collection;

class BusinessStateChangeReport
{
    public function __construct(
        public BusinessStateBaseline $current,
        public ?BusinessStateBaseline $previous,
        public Collection $changes,
        public Collection $attention
    ) {}

    public function hasComparisonBaseline(): bool
    {
        return $this->previous !== null;
    }
}
