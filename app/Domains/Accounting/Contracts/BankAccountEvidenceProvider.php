<?php

namespace App\Domains\Accounting\Contracts;

use Illuminate\Support\Collection;

interface BankAccountEvidenceProvider
{
    /**
     * @return Collection<int, object>
     */
    public function current(): Collection;
}
