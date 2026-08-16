<?php

namespace App\Domains\BusinessBrain\Investigation\Claims;

class HypothesisClaimSet
{
    /**
     * @param  array<int, HypothesisClaim>  $claims
     */
    public function __construct(
        public array $claims
    ) {}

    public function find(
        string $key
    ): ?HypothesisClaim {
        foreach ($this->claims as $claim) {
            if ($claim->key === $key) {
                return $claim;
            }
        }

        return null;
    }
}
