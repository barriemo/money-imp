<?php

namespace App\Domains\BusinessBrain\Contracts;

use Illuminate\Support\Collection;

interface BusinessObservationProvider
{
    public function observations(): Collection;
}
