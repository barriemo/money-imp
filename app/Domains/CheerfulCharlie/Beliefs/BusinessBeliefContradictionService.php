<?php

namespace App\Domains\CheerfulCharlie\Beliefs;

use App\Models\BusinessBelief;
use Illuminate\Support\Collection;

class BusinessBeliefContradictionService
{
    public function contradictions(
        BusinessBelief $belief
    ): Collection {
        return $belief
            ->evidence()
            ->where(
                'relationship',
                'contradicts'
            )
            ->orderByDesc(
                'weight'
            )
            ->get();
    }

    public function hasConflict(
        BusinessBelief $belief
    ): bool {
        return $this
            ->contradictions(
                $belief
            )
            ->isNotEmpty();
    }
}
