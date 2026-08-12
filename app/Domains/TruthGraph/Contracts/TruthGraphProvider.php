<?php

namespace App\Domains\TruthGraph\Contracts;

use App\Domains\TruthGraph\TruthGraphContribution;

interface TruthGraphProvider
{
    public function supports(
        string $rootType
    ): bool;

    public function build(
        string $rootId
    ): TruthGraphContribution;
}
