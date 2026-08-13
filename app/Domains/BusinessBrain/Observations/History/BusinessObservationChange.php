<?php

namespace App\Domains\BusinessBrain\Observations\History;

use App\Domains\BusinessBrain\Observations\BusinessObservation;

class BusinessObservationChange
{
    public function __construct(
        public string $type,

        public BusinessObservation $observation,

        public ?BusinessObservation $previous = null
    ) {}
}
