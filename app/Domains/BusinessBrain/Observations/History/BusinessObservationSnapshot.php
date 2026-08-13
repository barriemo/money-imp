<?php

namespace App\Domains\BusinessBrain\Observations\History;

use Illuminate\Support\Collection;

class BusinessObservationSnapshot
{
    public function __construct(
        public Collection $observations,

        public \DateTimeInterface $generatedAt
    ) {}
}
