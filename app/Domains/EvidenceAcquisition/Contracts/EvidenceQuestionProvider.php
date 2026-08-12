<?php

namespace App\Domains\EvidenceAcquisition\Contracts;

use Illuminate\Support\Collection;

interface EvidenceQuestionProvider
{
    public function questions(): Collection;
}
